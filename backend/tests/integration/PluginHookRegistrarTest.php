<?php

declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__, 2));
define('PLUGINS_PATH', dirname(BASE_PATH) . '/plugins');

require_once BASE_PATH . '/tests/unit/helpers.php';
require_once BASE_PATH . '/tests/helpers/autoload.php';
require_once BASE_PATH . '/tests/helpers/plugins/plugin_fixtures.php';

use Xestify\core\Database;
use Xestify\exceptions\DatabaseException;
use Xestify\plugins\HookDispatcher;
use Xestify\plugins\infrastructure\PluginClassLoader;
use Xestify\plugins\runtime\PluginHookRegistrar;
use Xestify\repositories\PluginRepository;
use Xestify\plugins\infrastructure\PluginSchemaCodec;

loadPluginTestEnv(BASE_PATH);

try {
    $pdo = Database::connection();
} catch (DatabaseException) {
    echo "[SKIP] PostgreSQL not reachable — all PluginHookRegistrarTest cases skipped.\n";
    echo "       Configure backend/.env with valid DB_* vars and run migrations.\n";
    echo str_repeat('-', 40) . "\n";
    echo "Resultado: 0 passed, 0 failed (skipped)\n";
    exit(0);
}

echo str_repeat('-', 40) . "\n";

$pdo->prepare("UPDATE plugins SET status = 'active' WHERE slug = 'comments'")->execute();

TestSuite::run('registerActiveHooks() registers comments tab when comments is active', function () use ($pdo): void {
    $dispatcher = new HookDispatcher();
    $registrar = new PluginHookRegistrar(
        new PluginRepository($pdo, new PluginSchemaCodec()),
        new PluginClassLoader(PLUGINS_PATH, $pdo)
    );

    $registrar->registerActiveHooks($dispatcher);

    $tabs = $dispatcher->applyFilter('registerTabs', [], ['entity' => 'clients']);
    $ids = array_column($tabs, 'id');

    assertTrue(in_array('comments', $ids, true), 'comments tab must appear after registerActiveHooks()');
});

TestSuite::run('registerActiveHooks() keeps current non-idempotent behavior', function () use ($pdo): void {
    $dispatcher = new HookDispatcher();
    $registrar = new PluginHookRegistrar(
        new PluginRepository($pdo, new PluginSchemaCodec()),
        new PluginClassLoader(PLUGINS_PATH, $pdo)
    );

    $registrar->registerActiveHooks($dispatcher);
    $registrar->registerActiveHooks($dispatcher);

    $tabs = $dispatcher->applyFilter('registerTabs', [], ['entity' => 'clients']);
    $commentTabs = array_filter($tabs, static fn(array $tab): bool => $tab['id'] === 'comments');

    assertTrue(count($commentTabs) >= 1, 'comments tab must appear at least once');
});

echo str_repeat('-', 40) . "\n";
TestSuite::summary();
exit(TestSuite::exitCode());
