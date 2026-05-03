<?php

declare(strict_types=1);

namespace Xestify\Database\Seeders;

use PDO;
use Xestify\core\Database;

/**
 * Legacy transition helpers for entity data.
 *
 * Entity definitions now come from installed plugins only. This class no
 * longer seeds catalog rows; it only migrates pre-refactor demo data.
 */
class EntitySeeder
{
    public static function migrateLegacyClientRecords(): void
    {
        $pdo = Database::connection();
        self::migrateRecords($pdo, 'client', 'clients');
    }

    private static function migrateRecords(PDO $pdo, string $from, string $to): void
    {
        $pdo->prepare(
            'UPDATE plugin_entity_data
                SET entity_slug = :to, updated_at = NOW()
              WHERE entity_slug = :from'
        )->execute([':to' => $to, ':from' => $from]);
    }
}
