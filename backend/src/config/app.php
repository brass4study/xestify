<?php

declare(strict_types=1);

use Xestify\controllers\AuthController;
use Xestify\controllers\EntityController;
use Xestify\core\Database;
use Xestify\database\Seeders\UserSeeder;
use Xestify\repositories\GenericRepository;
use Xestify\services\EntityService;
use Xestify\services\JwtService;
use Xestify\services\ValidationService;

// --- Database -----------------------------------------------------------------

$container->singleton(Database::class, fn() => Database::connection());

// Auto-seed on boot: inserts default admin only if users table is empty
UserSeeder::seedIfEmpty();

// --- JWT ----------------------------------------------------------------------

$container->singleton(JwtService::class, fn() => new JwtService(
    $_ENV['JWT_SECRET'] ?? 'changeme',
    (int) ($_ENV['JWT_EXPIRY'] ?? 3600)
));

// --- Entity layer -------------------------------------------------------------

$container->singleton(ValidationService::class, fn() => new ValidationService());

$container->singleton(GenericRepository::class, fn() => new GenericRepository(
    $container->get(Database::class)
));

$container->singleton(EntityService::class, fn() => new EntityService(
    $container->get(GenericRepository::class),
    $container->get(ValidationService::class),
    $container->get(Database::class)
));

// --- Controllers --------------------------------------------------------------

$container->singleton(AuthController::class, fn() => new AuthController(
    $container->get(JwtService::class)
));

$container->singleton(EntityController::class, fn() => new EntityController(
    $container->get(EntityService::class),
    $container->get(Database::class)
));
