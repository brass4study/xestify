<?php

declare(strict_types=1);

namespace Xestify\repositories;

use PDO;
use Xestify\plugins\discovery\PluginSchemaCodec;

/**
 * Read-only queries on the `plugins` table. Mutations live in
 * PluginWriteRepository (split out to keep both classes under the
 * method-count threshold — STORY 10.3 §2bis cleanup).
 */
final class PluginRepository
{
    private const SELECT_COLUMNS = '
        id, slug, status, manifest_json, schema_json, sort_order, installed_at, updated_at
    ';
    private const SELECT_ROW = 'SELECT ' . self::SELECT_COLUMNS;

    public function __construct(private PDO $pdo, private PluginSchemaCodec $schemaCodec)
    {
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listInstalled(): array
    {
        $stmt = $this->pdo->query(
            "SELECT p.slug,
                    p.manifest_json->>'label' AS name,
                    p.manifest_json->>'description' AS description,
                    p.manifest_json->>'type' AS plugin_type,
                    p.manifest_json->>'version' AS version,
                    p.status,
                    p.installed_at,
                    p.updated_at,
                    EXISTS (
                        SELECT 1
                        FROM plugin_update_history h
                        WHERE h.slug = p.slug
                          AND h.target_version = p.manifest_json->>'version'
                    ) AS can_rollback
             FROM plugins p
             ORDER BY p.sort_order ASC, p.slug ASC"
        );
        if ($stmt === false) {
            return [];
        }

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * @return list<string>
     */
    public function listActivePluginNames(): array
    {
        $stmt = $this->pdo->query("SELECT manifest_json->>'name' AS plugin_name FROM plugins WHERE status = 'active'");
        if ($stmt === false) {
            return [];
        }

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return $this->extractColumn($rows, 'plugin_name');
    }

    /**
     * @return list<string>
     */
    public function listActiveEntitySlugs(): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT slug
             FROM plugins
             WHERE status = :status
               AND manifest_json->>'type' = :plugin_type
             ORDER BY slug ASC"
        );
        $stmt->execute([
            ':status' => 'active',
            ':plugin_type' => 'entity',
        ]);

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return $this->extractColumn($rows, 'slug');
    }

    /**
     * Lightweight projection consumed by PluginOutdatedService — aliased to
     * the same key names a full decoded row would use, so that consumer
     * needs no changes even though these come straight from manifest_json.
     *
     * @return array<int, array<string, mixed>>
     */
    public function listInstalledVersions(): array
    {
        $stmt = $this->pdo->query(
            "SELECT manifest_json->>'name' AS plugin_name,
                    slug,
                    manifest_json->>'label' AS name,
                    manifest_json->>'type' AS plugin_type,
                    manifest_json->>'version' AS version
             FROM plugins"
        );
        if ($stmt === false) {
            return [];
        }

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findBySlug(string $slug): ?array
    {
        $stmt = $this->pdo->prepare(
            self::SELECT_ROW . '
             FROM plugins
             WHERE slug = :slug
             LIMIT 1'
        );
        $stmt->execute([':slug' => $slug]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $this->decodeRow($row);
    }

    /**
     * plugin_name is not unique: the same disk folder can be registered as
     * several independent instances (each with its own slug/data), so this
     * returns every matching row, not just one.
     *
     * @return array<int, array<string, mixed>>
     */
    public function findAllByPluginName(string $pluginName): array
    {
        $stmt = $this->pdo->prepare(
            self::SELECT_ROW . "
             FROM plugins
             WHERE manifest_json->>'name' = :plugin_name
             ORDER BY installed_at ASC"
        );
        $stmt->execute([':plugin_name' => $pluginName]);

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return array_map($this->decodeRow(...), $rows);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function lockBySlug(string $slug): ?array
    {
        $stmt = $this->pdo->prepare(
            self::SELECT_ROW . '
             FROM plugins
             WHERE slug = :slug
             LIMIT 1
             FOR UPDATE'
        );
        $stmt->execute([':slug' => $slug]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $this->decodeRow($row);
    }

    /**
     * Locks and returns the row immediately adjacent to ($sortOrder, $slug)
     * among plugins of the same $pluginType (display order, entity and
     * extension order are independent concerns — entity order drives the
     * nav menu, extension order drives tab priority — so a plugin never
     * swaps sort_order with one of the other type), same tie-break as
     * listInstalled(): sort_order ASC, slug ASC. $direction < 0 looks
     * backward (the "move up" neighbor), $direction > 0 looks forward (the
     * "move down" neighbor). Returns null when $slug is already at that end
     * of its type's list (nothing to swap with).
     *
     * @return array<string, mixed>|null
     */
    public function lockNeighborBySortOrder(int $sortOrder, string $slug, int $direction, string $pluginType): ?array
    {
        $comparator = $direction < 0 ? '<' : '>';
        $orderDirection = $direction < 0 ? 'DESC' : 'ASC';

        $stmt = $this->pdo->prepare(
            self::SELECT_ROW . "
             FROM plugins
             WHERE manifest_json->>'type' = :plugin_type
               AND (sort_order, slug) {$comparator} (:sort_order, :slug)
             ORDER BY sort_order {$orderDirection}, slug {$orderDirection}
             LIMIT 1
             FOR UPDATE"
        );
        $stmt->execute([':plugin_type' => $pluginType, ':sort_order' => $sortOrder, ':slug' => $slug]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $this->decodeRow($row);
    }

    /**
     * plugin_name is not unique, so "the installed version" of a dependency
     * means the highest version among all its instances — the most
     * permissive reading of "some installation of B satisfies >= X".
     */
    public function findInstalledVersion(string $pluginName): ?string
    {
        $stmt = $this->pdo->prepare(
            "SELECT manifest_json->>'version' AS version FROM plugins WHERE manifest_json->>'name' = :plugin_name"
        );
        $stmt->execute([':plugin_name' => $pluginName]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $best = null;
        foreach ($rows as $row) {
            $version = $row['version'] ?? null;
            if (!is_string($version) && !is_numeric($version)) {
                continue;
            }

            $version = (string) $version;
            if ($best === null || version_compare($version, $best, '>')) {
                $best = $version;
            }
        }

        return $best;
    }

    /**
     * @param array<string, mixed> $rows
     * @return list<string>
     */
    private function extractColumn(array $rows, string $column): array
    {
        return array_values(array_map(
            static fn(array $row): string => (string) ($row[$column] ?? ''),
            $rows
        ));
    }

    /**
     * Decodes manifest_json (stored/fetched as a JSON string, like every
     * other JSONB column) into a PHP array so every caller of a full-row
     * method always receives $row['manifest_json'] ready to index into.
     *
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function decodeRow(array $row): array
    {
        $decoded = $this->schemaCodec->decode($row['manifest_json'] ?? null);
        $row['manifest_json'] = is_array($decoded) ? $decoded : [];

        return $row;
    }
}
