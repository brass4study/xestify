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
    /** @var array<array{slug:string,name:string,fields:array<mixed>}> */
    private const ENTITIES = [
        [
            'slug'   => 'client',
            'name'   => 'Clientes',
            'fields' => [
                ['name' => 'name',    'label' => 'Nombre',   'type' => 'string', 'required' => true],
                ['name' => 'email',   'label' => 'Email',    'type' => 'email',  'required' => true],
                ['name' => 'phone',   'label' => 'Teléfono', 'type' => 'string', 'required' => false],
            ],
        ],
        [
            'slug'   => 'product',
            'name'   => 'Productos',
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
            return;
        }

        foreach (self::ENTITIES as $entity) {
            $schemaJson = json_encode(['fields' => $entity['fields']]);
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
    }
}
