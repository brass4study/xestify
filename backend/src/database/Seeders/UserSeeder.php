<?php

declare(strict_types=1);

namespace Xestify\Database\Seeders;

use PDO;
use Xestify\core\Database;

/**
 * Seeds the users table with a default admin account.
 * Auto-runs on server boot only when the table is empty.
 */
class UserSeeder
{
    public static function seedIfEmpty(): void
    {
        $pdo = Database::connection();

        $stmt = $pdo->query('SELECT COUNT(*) FROM users');
        if ($stmt === false) {
            return;
        }

        $count = (int) $stmt->fetchColumn();
        if ($count > 0) {
            return;
        }

        $hash = password_hash('admin123', PASSWORD_BCRYPT);

        $stmt = $pdo->prepare(
            'INSERT INTO users (email, password_hash, roles)
             VALUES (:email, :hash, :roles)
             ON CONFLICT DO NOTHING'
        );

        $stmt->execute([
            ':email' => 'admin@xestify.local',
            ':hash'  => $hash,
            ':roles' => '["admin"]',
        ]);
    }
}
