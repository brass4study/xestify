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
use Xestify\plugins\application\InstalledPluginSchemaValidator;
use Xestify\plugins\application\PluginSyncService;
use Xestify\plugins\application\PluginStatusService;
use Xestify\plugins\infrastructure\PluginCompatibilityValidator;
use Xestify\plugins\infrastructure\PluginDependencyValidator;
use Xestify\plugins\infrastructure\PluginDiscoveryService;
use Xestify\plugins\infrastructure\PluginManifestReader;
use Xestify\plugins\infrastructure\PluginSchemaReader;
use Xestify\plugins\infrastructure\PluginSourceService;
use Xestify\plugins\runtime\PluginLifecycleInvoker;

loadPluginTestEnv(BASE_PATH);

try {
    $pdo = Database::connection();
} catch (DatabaseException) {
    echo "[SKIP] PostgreSQL not reachable — all PluginBootTest cases skipped.\n";
    echo "       Configure backend/.env with valid DB_* vars and run migrations.\n";
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
        new PluginLifecycleInvoker(new PluginClassLoader(PLUGINS_PATH, $pdo)),
        new PluginSchemaCodec(),
        new InstalledPluginSchemaValidator()
    );
}

function buildBootStatusService(\PDO $pdo): PluginStatusService
{
    return new PluginStatusService(
        $pdo,
        new PluginRepository($pdo, new PluginSchemaCodec()),
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

    $tabs = $dispatcher->applyFilter('registerTabs', [], ['entity' => 'clients']);
    $ids = array_column($tabs, 'id');

    assertTrue(in_array('comments', $ids, true), 'comments tab must appear after registerActiveHooks()');
});

TestSuite::run('registerActiveHooks() preserves current repeated-registration behavior', function () use ($pdo): void {
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

TestSuite::run('tab endpoint contains entity placeholder', function () use ($pdo): void {
    $dispatcher = new HookDispatcher();
    $registrar = new PluginHookRegistrar(
        new PluginRepository($pdo, new PluginSchemaCodec()),
        new PluginClassLoader(PLUGINS_PATH, $pdo)
    );

    $registrar->registerActiveHooks($dispatcher);

    $tabs = $dispatcher->applyFilter('registerTabs', [], ['entity' => 'clients']);
    $found = array_values(array_filter($tabs, static fn(array $tab): bool => $tab['id'] === 'comments'));

    assertTrue(!empty($found), 'comments tab must exist');
    assertTrue(str_contains($found[0]['endpoint'] ?? '', 'clients'), 'endpoint must contain entity slug');
    assertTrue(str_contains($found[0]['endpoint'] ?? '', '{id}'), 'endpoint must contain {id} placeholder');
});

echo str_repeat('-', 40) . "\n";
TestSuite::summary();
exit(TestSuite::exitCode());
