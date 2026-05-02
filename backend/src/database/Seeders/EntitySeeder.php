<?php

declare(strict_types=1);

namespace Xestify\Database\Seeders;

use PDO;
use Xestify\core\Database;

/**
 * Seeds entity plugins with demo entity types.
 * Auto-runs on server boot only when no entity plugins exist.
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

        $stmt = $pdo->query("SELECT COUNT(*) FROM plugins WHERE plugin_type = 'entity'");
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

            $stmtM = $pdo->prepare(
                "INSERT INTO plugins (slug, name, plugin_type, version, status, schema_version, schema_json)
                 VALUES (:slug, :name, 'entity', '1.0.0', 'active', 1, :schema)
                 ON CONFLICT (slug) DO UPDATE
                 SET name = EXCLUDED.name,
                     schema_json = EXCLUDED.schema_json,
                     schema_version = 1,
                     status = 'active',
                     updated_at = NOW()"
            );
            $stmtM->execute([
                ':slug'   => $entity['slug'],
                ':name'   => $entity['name'],
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

            $pdo->prepare(
                "INSERT INTO plugins (slug, name, plugin_type, version, status, schema_version, schema_json)
                 VALUES (:slug, :name, 'entity', '1.0.0', 'active', 1, :schema)
                 ON CONFLICT (slug) DO UPDATE
                 SET schema_json = EXCLUDED.schema_json,
                     schema_version = CASE
                         WHEN plugins.schema_json IS DISTINCT FROM EXCLUDED.schema_json THEN plugins.schema_version + 1
                         ELSE plugins.schema_version
                     END,
                     status = 'active',
                     updated_at = NOW()"
            )->execute([
                ':slug'   => $entity['slug'],
                ':name'   => $entity['name'],
                ':schema' => $schemaJson,
            ]);
        }
    }
}
