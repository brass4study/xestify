<?php

declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__, 2));

require_once BASE_PATH . '/tests/unit/helpers.php';
require_once BASE_PATH . '/tests/helpers/autoload.php';
require_once BASE_PATH . '/tests/helpers/plugins/plugin_fixtures.php';
require_once BASE_PATH . '/tests/helpers/plugins/plugin_services.php';

use Xestify\core\Database;
use Xestify\exceptions\DatabaseException;

loadPluginTestEnv(BASE_PATH);

try {
    $pdo = Database::connection();
} catch (DatabaseException) {
    echo "[SKIP] PostgreSQL not reachable — all PluginLifecycleTest cases skipped.\n";
    echo "       Configure backend/.env with valid DB_* vars and run migrations.\n";
    echo str_repeat('-', 40) . "\n";
    echo "Resultado: 0 passed, 0 failed (skipped)\n";
    exit(0);
}

const LC_STATUS_QUERY = 'SELECT status FROM plugins WHERE slug = :slug';

$slug = 'lc' . bin2hex(random_bytes(4));

$lifecycle = <<<PHP
<?php
declare(strict_types=1);
namespace Xestify\plugins\\{$slug};
use PDO;
use Xestify\plugins\contracts\PluginLifecycleInterface;
final class Lifecycle implements PluginLifecycleInterface {
    public function __construct(private PDO \$pdo) {}
    public function onInstall(): void { \$GLOBALS['lc_install'] = (\$GLOBALS['lc_install'] ?? 0) + 1; }
    public function onActivate(): void { \$GLOBALS['lc_activate'] = (\$GLOBALS['lc_activate'] ?? 0) + 1; }
    public function onDeactivate(): void { \$GLOBALS['lc_deactivate'] = (\$GLOBALS['lc_deactivate'] ?? 0) + 1; }
}
PHP;

$root = createPluginFixture([
    'name' => $slug,
    'label' => 'Test Lifecycle Plugin',
    'version' => '1.0.0',
    'type' => 'entity',
    'core_version' => '1.0.0',
], false, false, null, $lifecycle);

TestSuite::run('onInstall se ejecuta al sincronizar un plugin nuevo', function () use ($pdo, $root): void {
    $GLOBALS['lc_install'] = 0;
    $service = buildPluginSyncService($root, $pdo);
    $service->syncAll();
    assertEquals(1, $GLOBALS['lc_install'] ?? 0, 'onInstall must run exactly once');
});

TestSuite::run('onInstall no se repite en sincronizaciones posteriores', function () use ($pdo, $root): void {
    $GLOBALS['lc_install'] = 0;
    $service = buildPluginSyncService($root, $pdo);
    $service->syncAll();
    assertEquals(0, $GLOBALS['lc_install'] ?? 0, 'onInstall must not repeat for installed plugin');
});

TestSuite::run('activate() actualiza estado e invoca onActivate()', function () use ($pdo, $root, $slug): void {
    $GLOBALS['lc_activate'] = 0;
    $service = buildPluginStatusService($root, $pdo);
    $service->activate($slug);

    $stmt = $pdo->prepare(LC_STATUS_QUERY);
    $stmt->execute([':slug' => $slug]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    assertEquals('active', $row['status'] ?? null, 'status must be active after activate()');
    assertEquals(1, $GLOBALS['lc_activate'] ?? 0, 'onActivate must be called once');
});

TestSuite::run('deactivate() actualiza estado e invoca onDeactivate()', function () use ($pdo, $root, $slug): void {
    $GLOBALS['lc_deactivate'] = 0;
    $service = buildPluginStatusService($root, $pdo);
    $service->deactivate($slug);

    $stmt = $pdo->prepare(LC_STATUS_QUERY);
    $stmt->execute([':slug' => $slug]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    assertEquals('inactive', $row['status'] ?? null, 'status must be inactive after deactivate()');
    assertEquals(1, $GLOBALS['lc_deactivate'] ?? 0, 'onDeactivate must be called once');
});

TestSuite::run('activate() y deactivate() mantienen el ciclo completo', function () use ($pdo, $root, $slug): void {
    $GLOBALS['lc_activate'] = 0;
    $GLOBALS['lc_deactivate'] = 0;
    $service = buildPluginStatusService($root, $pdo);

    $service->activate($slug);
    $service->deactivate($slug);
    $service->activate($slug);

    assertEquals(2, $GLOBALS['lc_activate'] ?? 0, 'onActivate must be called twice');
    assertEquals(1, $GLOBALS['lc_deactivate'] ?? 0, 'onDeactivate must be called once');
});

cleanupPluginRecord($pdo, $slug);
removePluginFixture($root);

TestSuite::summary();
exit(TestSuite::exitCode());
