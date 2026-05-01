<?php

declare(strict_types=1);

namespace Xestify\plugins\clients;

use PDO;
use PDOException;
use Xestify\exceptions\PluginException;

/**
 * Installer for the clients plugin.
 *
 * Registers the "clients" entity in system_entities and seeds its
 * schema definition into entity_metadata.
 *
 * Idempotent: safe to run multiple times (uses INSERT … ON CONFLICT DO NOTHING).
 */
final class Installer
{
    private const ENTITY_SLUG  = 'clients';
    private const ENTITY_NAME  = 'Clientes';
    private const PLUGIN_SLUG  = 'clients';
    private const SCHEMA_VERSION = 1;

    public function __construct(private PDO $pdo)
    {
    }

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
                'clients installer failed: ' . $e->getMessage(),
                (int) $e->getCode(),
                $e
            );
        }
    }

    private function registerEntity(): void
    {
        $this->pdo->prepare(
            'INSERT INTO system_entities (slug, name, source_plugin_slug, is_active)
             VALUES (:slug, :name, :plugin, true)
             ON CONFLICT (slug) DO UPDATE
               SET name              = EXCLUDED.name,
                   source_plugin_slug = EXCLUDED.source_plugin_slug,
                   updated_at        = NOW()'
        )->execute([
            ':slug'   => self::ENTITY_SLUG,
            ':name'   => self::ENTITY_NAME,
            ':plugin' => self::PLUGIN_SLUG,
        ]);
    }

    private function seedSchema(): void
    {
        $schemaPath = __DIR__ . '/schema.json';
        $raw = file_get_contents($schemaPath);

        if ($raw === false) {
            throw new PluginException('clients: schema.json not found at ' . $schemaPath);
        }

        $decoded = json_decode($raw, true);

        if (!is_array($decoded) || !isset($decoded['fields'])) {
            throw new PluginException('clients: schema.json is invalid or missing "fields" key');
        }

        $schemaJson = json_encode(['fields' => $decoded['fields']]);

        if ($schemaJson === false) {
            throw new PluginException('clients: failed to re-encode schema JSON');
        }

        $this->pdo->prepare(
            'INSERT INTO entity_metadata (entity_slug, schema_version, schema_json)
             VALUES (:slug, :version, :schema)
             ON CONFLICT DO NOTHING'
        )->execute([
            ':slug'    => self::ENTITY_SLUG,
            ':version' => self::SCHEMA_VERSION,
            ':schema'  => $schemaJson,
        ]);
    }
}
