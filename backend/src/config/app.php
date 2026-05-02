<?php

declare(strict_types=1);

use Xestify\controllers\AuthController;
use Xestify\controllers\CommentsController;
use Xestify\controllers\EntityController;
use Xestify\core\Container;
use Xestify\core\Database;
use Xestify\database\Seeders\EntitySeeder;
use Xestify\database\Seeders\UserSeeder;
use Xestify\plugins\HookDispatcher;
use Xestify\repositories\GenericRepository;
use Xestify\services\EntityService;
use Xestify\services\JwtService;
use Xestify\services\ValidationService;

$container = isset($container) ? $container : null;
if (!($container instanceof Container)) {
    return;
}

/** @var Container $container injected by bootstrap.php */

// --- Database -----------------------------------------------------------------

$container->singleton(Database::class, fn() => Database::connection());

// Auto-seed on boot: inserts default admin only if users table is empty
UserSeeder::seedIfEmpty();

// Auto-seed on boot: inserts demo entity types if system_entities is empty
EntitySeeder::seedIfEmpty();

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

// --- Plugins ------------------------------------------------------------------

$container->singleton(HookDispatcher::class, fn() => new HookDispatcher());

// --- Controllers --------------------------------------------------------------

$container->singleton(AuthController::class, fn() => new AuthController(
    $container->get(JwtService::class)
));

$container->singleton(EntityController::class, fn() => new EntityController(
    $container->get(EntityService::class),
    $container->get(Database::class),
    $container->get(HookDispatcher::class)
));

$container->singleton(CommentsController::class, fn() => new CommentsController(
    $container->get(Database::class)
));
