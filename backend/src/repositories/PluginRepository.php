<?php

declare(strict_types=1);

namespace Xestify\repositories;

use PDO;
use Xestify\exceptions\PluginException;
use Xestify\plugins\infrastructure\PluginSchemaCodec;

final class PluginRepository
{
    private const SELECT_COLUMNS = '
        id, slug, name, plugin_type, version, status, schema_version, schema_json, installed_at, updated_at
    ';

    public function __construct(private PDO $pdo, private PluginSchemaCodec $schemaCodec)
    {
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listInstalled(): array
    {
        $stmt = $this->pdo->query(
            'SELECT slug, name, plugin_type, version, status, schema_version, installed_at, updated_at
             FROM plugins
             ORDER BY slug ASC'
        );

        return $stmt ? ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];
    }

    /**
     * @return list<string>
     */
    public function listActiveSlugs(): array
    {
        $stmt = $this->pdo->query("SELECT slug FROM plugins WHERE status = 'active'");
        if ($stmt === false) {
            return [];
        }

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return array_values(array_map(static fn(array $row): string => (string) $row['slug'], $rows));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listInstalledVersions(): array
    {
        $stmt = $this->pdo->query('SELECT slug, name, plugin_type, version FROM plugins');

        return $stmt ? ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findBySlug(string $slug): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT ' . self::SELECT_COLUMNS . '
             FROM plugins
             WHERE slug = :slug
             LIMIT 1'
        );
        $stmt->execute([':slug' => $slug]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $row;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function lockBySlug(string $slug): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT ' . self::SELECT_COLUMNS . '
             FROM plugins
             WHERE slug = :slug
             LIMIT 1
             FOR UPDATE'
        );
        $stmt->execute([':slug' => $slug]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $row;
    }

    public function findInstalledVersion(string $slug): ?string
    {
        $stmt = $this->pdo->prepare('SELECT version FROM plugins WHERE slug = :slug');
        $stmt->execute([':slug' => $slug]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row === false || !isset($row['version'])) {
            return null;
        }

        return (string) $row['version'];
    }

    /**
     * @param array<string, mixed> $manifest
     * @param array<string, mixed>|null $schema
     */
    public function insert(array $manifest, ?array $schema): void
    {
        $schemaJson = $this->schemaCodec->encode($schema, (string) $manifest['slug']);

        $this->pdo->prepare(
            'INSERT INTO plugins (slug, name, plugin_type, version, status, schema_json, schema_version)
             VALUES (:slug, :name, :type, :version, :status, CAST(:schema AS jsonb), 1)'
        )->execute([
            ':slug' => $manifest['slug'],
            ':name' => $manifest['name'],
            ':type' => $manifest['type'],
            ':version' => $manifest['version'],
            ':status' => 'inactive',
            ':schema' => $schemaJson,
        ]);
    }

    public function refreshName(string $slug, string $name): void
    {
        $this->pdo->prepare(
            'UPDATE plugins
                SET name = :name,
                    updated_at = NOW()
              WHERE slug = :slug'
        )->execute([
            ':name' => $name,
            ':slug' => $slug,
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function updateStatus(string $slug, string $status): ?array
    {
        $stmt = $this->pdo->prepare(
            'UPDATE plugins SET status = :status, updated_at = NOW()
             WHERE slug = :slug
             RETURNING slug, name, plugin_type, version, status, schema_version, installed_at, updated_at'
        );
        $stmt->execute([
            ':status' => $status,
            ':slug' => $slug,
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $row;
    }

    /**
     * @param array<string, mixed> $current
     * @param array<string, mixed> $manifest
     * @param array<string, mixed>|null $mergedSchema
     * @return array<string, mixed>
     */
    public function persistUpdate(
        array $current,
        array $manifest,
        ?array $mergedSchema,
        bool $schemaChanged
    ): array {
        if ($schemaChanged) {
            $schemaJson = $this->schemaCodec->encode($mergedSchema, (string) $manifest['slug']);

            $stmt = $this->pdo->prepare(
                'UPDATE plugins
                    SET name = :name,
                        version = :version,
                        schema_json = CAST(:schema_json AS jsonb),
                        schema_version = schema_version + 1,
                        updated_at = NOW()
                  WHERE id = :id
                  RETURNING slug, name, plugin_type, version, status, schema_version, installed_at, updated_at'
            );
            $stmt->execute([
                ':id' => $current['id'],
                ':name' => $manifest['name'],
                ':version' => $manifest['version'],
                ':schema_json' => $schemaJson,
            ]);
        } else {
            $stmt = $this->pdo->prepare(
                'UPDATE plugins
                    SET name = :name,
                        version = :version,
                        updated_at = NOW()
                  WHERE id = :id
                  RETURNING slug, name, plugin_type, version, status, schema_version, installed_at, updated_at'
            );
            $stmt->execute([
                ':id' => $current['id'],
                ':name' => $manifest['name'],
                ':version' => $manifest['version'],
            ]);
        }

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            throw new PluginException("Failed to persist updated plugin '{$manifest['slug']}'.");
        }

        return $row;
    }
}
