<?php

declare(strict_types=1);

namespace Xestify\plugins\contracts;

use PDO;
use PDOException;
use Xestify\exceptions\PluginException;

/**
 * Base installer for an entity plugin: registers its row in `plugins` and
 * seeds `schema_json` from the plugin's own schema.json.
 *
 * Idempotent: safe to run multiple times (uses INSERT … ON CONFLICT DO UPDATE).
 *
 * A concrete installer only needs to say who it is (slug/name/schema
 * version) and where its schema.json lives — the latter can't be resolved
 * here because __DIR__ always points at this base class's own file, not the
 * subclass's directory.
 */
abstract class AbstractEntityInstaller
{
    public function __construct(protected PDO $pdo)
    {
    }

    abstract protected function entitySlug(): string;

    abstract protected function entityName(): string;

    abstract protected function schemaVersion(): int;

    abstract protected function schemaPath(): string;

    /**
     * Run the installer: register entity + seed schema.
     *
     * @throws PluginException on DB failure
     */
    public function install(): void
    {
        try {
            $this->registerEntity();
            $this->seedSchema();
        } catch (PDOException $e) {
            throw new PluginException(
                $this->entitySlug() . ' installer failed: ' . $e->getMessage(),
                (int) $e->getCode(),
                $e
            );
        }
    }

    private function registerEntity(): void
    {
        $this->pdo->prepare(
            "INSERT INTO plugins (slug, name, plugin_type, version, status)
             VALUES (:slug, :name, 'entity', '1.0.0', 'active')
             ON CONFLICT (slug) DO UPDATE
               SET name       = EXCLUDED.name,
                   status     = 'active',
                   updated_at = NOW()"
        )->execute([
            ':slug' => $this->entitySlug(),
            ':name' => $this->entityName(),
        ]);
    }

    private function seedSchema(): void
    {
        $schemaPath = $this->schemaPath();
        $raw = file_get_contents($schemaPath);

        if ($raw === false) {
            throw new PluginException($this->entitySlug() . ': schema.json not found at ' . $schemaPath);
        }

        $decoded = json_decode($raw, true);

        if (!is_array($decoded) || !isset($decoded['fields'])) {
            throw new PluginException($this->entitySlug() . ': schema.json is invalid or missing "fields" key');
        }

        $schemaJson = json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if ($schemaJson === false) {
            throw new PluginException($this->entitySlug() . ': failed to re-encode schema JSON');
        }

        $this->pdo->prepare(
            'UPDATE plugins
             SET schema_json = :schema, schema_version = :version, updated_at = NOW()
             WHERE slug = :slug'
        )->execute([
            ':slug'    => $this->entitySlug(),
            ':version' => $this->schemaVersion(),
            ':schema'  => $schemaJson,
        ]);
    }
}
