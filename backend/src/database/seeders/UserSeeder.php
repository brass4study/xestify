<?php

declare(strict_types=1);

namespace Xestify\database\seeders;

use Xestify\core\Database;

/**
 * Seeds the users table with the fixed admin/normal accounts used by the STORY 10.1
 * quick-access login buttons. Each account is inserted independently and is
 * idempotent via `ON CONFLICT (email) DO NOTHING`, so re-running this (or seeding a
 * database that already has other users) never duplicates or skips either one.
 * Not invoked automatically on server boot — run manually via
 * `php tools/setup/seed-admin-user.php`.
 */
class UserSeeder
{
    public static function seedIfEmpty(): void
    {
        $pdo = Database::connection();

        $stmt = $pdo->prepare(
            'INSERT INTO users (email, password_hash, roles, name, is_seed)
             VALUES (:email, :hash, :roles, :name, TRUE)
             ON CONFLICT (email) DO NOTHING'
        );

        $stmt->execute([
            ':email' => 'admin@xestify.local',
            ':hash'  => password_hash('admin123', PASSWORD_BCRYPT),
            ':roles' => '["admin"]',
            ':name'  => 'Administrator',
        ]);

        $stmt->execute([
            ':email' => 'usuario@xestify.local',
            ':hash'  => password_hash('usuario123', PASSWORD_BCRYPT),
            ':roles' => '["operador"]',
            ':name'  => 'Usuario',
        ]);
    }
}
