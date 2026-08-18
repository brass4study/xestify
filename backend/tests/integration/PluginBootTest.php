<?php

declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__, 2));
define('PLUGINS_PATH', dirname(BASE_PATH) . '/plugins');

require_once BASE_PATH . '/tests/unit/helpers.php';
require_once BASE_PATH . '/tests/helpers/autoload.php';
require_once BASE_PATH . '/tests/helpers/plugins/plugin_fixtures.php';

use Xestify\core\Database;
use Xestify\exceptions\DatabaseException;
use Xestify\core\HookDispatcher;
use Xestify\plugins\lifecycle\PluginClassLoader;
use Xestify\plugins\lifecycle\PluginHookRegistrar;
use Xestify\repositories\PluginRepository;
use Xestify\repositories\PluginWriteRepository;
use Xestify\plugins\discovery\PluginSchemaCodec;
use Xestify\plugins\schema\InstalledPluginSchemaValidator;
use Xestify\plugins\lifecycle\PluginSyncService;
use Xestify\plugins\lifecycle\PluginStatusService;
use Xestify\plugins\guards\PluginCompatibilityValidator;
use Xestify\plugins\guards\PluginDependencyValidator;
use Xestify\plugins\discovery\PluginDiscoveryService;
use Xestify\plugins\discovery\PluginManifestReader;
use Xestify\plugins\discovery\PluginSchemaReader;
use Xestify\plugins\discovery\PluginSourceService;
use Xestify\plugins\lifecycle\PluginLifecycleInvoker;

loadPluginTestEnv(BASE_PATH);

try {
    $pdo = Database::connection();
} catch (DatabaseException) {
    echo "[SKIP] PostgreSQL not reachable — all PluginBootTest cases skipped.\n";
    echo "       Configure backend/.env with valid DB_* vars and run php tools/setup/install.php.\n";
    echo str_repeat('-', 40) . "\n";
    echo "Resultado: 0 passed, 0 failed (skipped)\n";
    exit(0);
}

function buildBootSyncService(\PDO $pdo): PluginSyncService
{
    $repository = new PluginRepository($pdo, new PluginSchemaCodec());
    $source = new PluginSourceService(
        new PluginDiscoveryService(PLUGINS_PATH),
        new PluginManifestReader(PLUGINS_PATH),
        new PluginSchemaReader(PLUGINS_PATH),
        new PluginCompatibilityValidator(),
        new PluginDependencyValidator($repository)
    );

    return new PluginSyncService(
        $pdo,
        $source,
        $repository,
        new PluginWriteRepository($pdo, new PluginSchemaCodec()),
        new PluginLifecycleInvoker(new PluginClassLoader(PLUGINS_PATH, $pdo)),
        new PluginSchemaCodec(),
        new InstalledPluginSchemaValidator()
    );
}

function buildBootStatusService(\PDO $pdo): PluginStatusService
{
    return new PluginStatusService(
        $pdo,
        new PluginWriteRepository($pdo, new PluginSchemaCodec()),
        new PluginLifecycleInvoker(new PluginClassLoader(PLUGINS_PATH, $pdo))
    );
}

echo str_repeat('-', 40) . "\n";

buildBootSyncService($pdo)->syncAll();
buildBootStatusService($pdo)->activate('comments');

TestSuite::run('registerActiveHooks() registers comments tab when comments is active', function () use ($pdo): void {
    $dispatcher = new HookDispatcher();
    $registrar = new PluginHookRegistrar(
        new PluginRepository($pdo, new PluginSchemaCodec()),
        new PluginClassLoader(PLUGINS_PATH, $pdo)
    );

    $registrar->registerActiveHooks($dispatcher);

    $tabs = $dispatcher->applyFilter('registerTabs', [], ['entity' => 'persons']);
    $ids = array_column($tabs, 'id');

    assertTrue(in_array('comments', $ids, true), 'comments tab must appear after registerActiveHooks()');
});

TestSuite::run('registerActiveHooks() is idempotent — repeated calls do not duplicate a plugin\'s hooks', function () use ($pdo): void {
    $dispatcher = new HookDispatcher();
    $registrar = new PluginHookRegistrar(
        new PluginRepository($pdo, new PluginSchemaCodec()),
        new PluginClassLoader(PLUGINS_PATH, $pdo)
    );

    $registrar->registerActiveHooks($dispatcher);
    $registrar->registerActiveHooks($dispatcher);

    $tabs = $dispatcher->applyFilter('registerTabs', [], ['entity' => 'persons']);
    $commentTabs = array_filter($tabs, static fn(array $tab): bool => $tab['id'] === 'comments');

    assertTrue(count($commentTabs) === 1, 'comments tab must appear exactly once, not once per registerActiveHooks() call');
});

TestSuite::run('tab endpoint contains entity placeholder', function () use ($pdo): void {
    $dispatcher = new HookDispatcher();
    $registrar = new PluginHookRegistrar(
        new PluginRepository($pdo, new PluginSchemaCodec()),
        new PluginClassLoader(PLUGINS_PATH, $pdo)
    );

    $registrar->registerActiveHooks($dispatcher);

    $tabs = $dispatcher->applyFilter('registerTabs', [], ['entity' => 'persons']);
    $found = array_values(array_filter($tabs, static fn(array $tab): bool => $tab['id'] === 'comments'));

    assertTrue(!empty($found), 'comments tab must exist');
    assertTrue(str_contains($found[0]['endpoint'] ?? '', 'persons'), 'endpoint must contain entity slug');
    assertTrue(str_contains($found[0]['endpoint'] ?? '', '{id}'), 'endpoint must contain {id} placeholder');
});

echo str_repeat('-', 40) . "\n";
TestSuite::summary();
exit(TestSuite::exitCode());
