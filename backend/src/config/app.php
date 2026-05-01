<?php

declare(strict_types=1);

use Xestify\controllers\AuthController;
use Xestify\core\Database;
use Xestify\database\Seeders\UserSeeder;
use Xestify\services\JwtService;

// --- Database -----------------------------------------------------------------

$container->singleton(Database::class, fn() => Database::connection());

// Auto-seed on boot: inserts default admin only if users table is empty
UserSeeder::seedIfEmpty();

// --- JWT ----------------------------------------------------------------------

$container->singleton(JwtService::class, fn() => new JwtService(
    $_ENV['JWT_SECRET'] ?? 'changeme',
    (int) ($_ENV['JWT_EXPIRY'] ?? 3600)
));

// --- Controllers --------------------------------------------------------------

$container->singleton(AuthController::class, fn() => new AuthController(
    $container->get(JwtService::class)
));
