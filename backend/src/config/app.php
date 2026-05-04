<?php

declare(strict_types=1);

use Xestify\controllers\AuthController;
use Xestify\controllers\EntityController;
use Xestify\controllers\PluginExtensionController;
use Xestify\controllers\PluginManagerController;
use Xestify\core\Container;
use Xestify\core\Database;
use Xestify\database\Seeders\EntitySeeder;
use Xestify\database\Seeders\UserSeeder;
use Xestify\middleware\AuthMiddleware;
use Xestify\plugins\HookDispatcher;
use Xestify\plugins\PluginLoader;
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

// --- JWT ----------------------------------------------------------------------

$container->singleton(JwtService::class, fn() => new JwtService(
    $_ENV['JWT_SECRET'] ?? 'changeme',
    (int) ($_ENV['JWT_EXPIRY'] ?? 3600)
));

$container->singleton(AuthMiddleware::class, fn() => new AuthMiddleware(
    $container->get(JwtService::class)
));

// --- Entity layer -------------------------------------------------------------

$container->singleton(HookDispatcher::class, fn() => new HookDispatcher());

$container->singleton(ValidationService::class, fn() => new ValidationService());

$container->singleton(GenericRepository::class, fn() => new GenericRepository(
    $container->get(Database::class)
));

$container->singleton(EntityService::class, fn() => new EntityService(
    $container->get(GenericRepository::class),
    $container->get(ValidationService::class),
    $container->get(Database::class),
    $container->get(HookDispatcher::class)
));

// --- Plugins ------------------------------------------------------------------

$container->singleton(PluginLoader::class, fn() => new PluginLoader(
    dirname(BASE_PATH) . '/plugins',
    $container->get(Database::class)
));

// Discover local plugins before registering hooks. This keeps plugins as the
// entity catalog source of truth while preserving existing activation state.
/** @var PluginLoader $pluginLoader */
$pluginLoader = $container->get(PluginLoader::class);
$pluginLoader->loadAll();

// Backward-compatible transition: move old singular demo records to the
// canonical clients entity slug.
EntitySeeder::migrateLegacyClientRecords();

// Register active plugin hooks at boot.
$pluginLoader->registerActiveHooks($container->get(HookDispatcher::class));

// --- Controllers --------------------------------------------------------------

$container->singleton(AuthController::class, fn() => new AuthController(
    $container->get(JwtService::class)
));

$container->singleton(EntityController::class, fn() => new EntityController(
    $container->get(EntityService::class),
    $container->get(Database::class),
    $container->get(HookDispatcher::class)
));

$container->singleton(PluginExtensionController::class, fn() => new PluginExtensionController(
    $container->get(Database::class)
));

$container->singleton(PluginManagerController::class, fn() => new PluginManagerController(
    $container->get(Database::class)
));
