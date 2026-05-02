<?php

declare(strict_types=1);

namespace Xestify\Database\Seeders;

use PDO;
use Xestify\core\Database;

/**
 * Seeds system_entities and entity_metadata with demo entity types.
 * Auto-runs on server boot only when system_entities is empty.
 */
class EntitySeeder
{
    /** @var array<array{slug:string,name:string,label_singular:string,fields:array<mixed>}> */
    private const ENTITIES = [
        [
            'slug'   => 'client',
            'name'   => 'Clientes',
            'label_singular' => 'cliente',
            'fields' => [
                ['name' => 'name',    'label' => 'Nombre',   'type' => 'string', 'required' => true],
                ['name' => 'email',   'label' => 'Email',    'type' => 'email',  'required' => true],
                ['name' => 'phone',   'label' => 'Teléfono', 'type' => 'string', 'required' => false],
            ],
        ],
        [
            'slug'   => 'product',
            'name'   => 'Productos',
            'label_singular' => 'producto',
            'fields' => [
                ['name' => 'name',        'label' => 'Nombre',      'type' => 'string',  'required' => true],
                ['name' => 'price',       'label' => 'Precio',      'type' => 'number',  'required' => true],
                ['name' => 'description', 'label' => 'Descripción', 'type' => 'text',    'required' => false],
            ],
        ],
    ];

    public static function seedIfEmpty(): void
    {
        $pdo = Database::connection();

        $stmt = $pdo->query('SELECT COUNT(*) FROM system_entities');
        if ($stmt === false) {
            return;
        }

        $count = (int) $stmt->fetchColumn();
        if ($count > 0) {
            self::ensureSingularLabels($pdo);
            return;
        }

        foreach (self::ENTITIES as $entity) {
            $schemaJson = json_encode([
                'label_singular' => $entity['label_singular'],
                'fields' => $entity['fields'],
            ]);
            if ($schemaJson === false) {
                continue;
            }

            $stmtE = $pdo->prepare(
                'INSERT INTO system_entities (slug, name, is_active)
                 VALUES (:slug, :name, true)
                 ON CONFLICT (slug) DO NOTHING'
            );
            $stmtE->execute([
                ':slug' => $entity['slug'],
                ':name' => $entity['name'],
            ]);

            $stmtM = $pdo->prepare(
                'INSERT INTO entity_metadata (entity_slug, schema_version, schema_json)
                 VALUES (:slug, 1, :schema)
                 ON CONFLICT DO NOTHING'
            );
            $stmtM->execute([
                ':slug'   => $entity['slug'],
                ':schema' => $schemaJson,
            ]);
        }

        self::ensureSingularLabels($pdo);
    }

    private static function ensureSingularLabels(PDO $pdo): void
    {
        foreach (self::ENTITIES as $entity) {
            $schemaJson = json_encode([
                'label_singular' => $entity['label_singular'],
                'fields' => $entity['fields'],
            ]);
            if ($schemaJson === false) {
                continue;
            }

            $stmt = $pdo->prepare(
                'SELECT schema_json, schema_version
                 FROM entity_metadata
                 WHERE entity_slug = :slug
                 ORDER BY schema_version DESC
                 LIMIT 1'
            );
            $stmt->execute([':slug' => $entity['slug']]);
            $row = $stmt->fetch();

            if ($row === false) {
                $insert = $pdo->prepare(
                    'INSERT INTO entity_metadata (entity_slug, schema_version, schema_json)
                     VALUES (:slug, 1, :schema)'
                );
                $insert->execute([
                    ':slug' => $entity['slug'],
                    ':schema' => $schemaJson,
                ]);
                continue;
            }

            $current = json_decode((string) ($row['schema_json'] ?? '{}'), true);
            $currentSingular = is_array($current) && isset($current['label_singular'])
                ? (string) $current['label_singular']
                : '';

            if ($currentSingular === $entity['label_singular']) {
                continue;
            }

            $nextVersion = (int) ($row['schema_version'] ?? 1) + 1;
            $insert = $pdo->prepare(
                'INSERT INTO entity_metadata (entity_slug, schema_version, schema_json)
                 VALUES (:slug, :version, :schema)'
            );
            $insert->execute([
                ':slug' => $entity['slug'],
                ':version' => $nextVersion,
                ':schema' => $schemaJson,
            ]);
        }
    }
}
