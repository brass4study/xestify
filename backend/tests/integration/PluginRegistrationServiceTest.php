<?php

declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__, 2));

require_once BASE_PATH . '/tests/unit/helpers.php';
require_once BASE_PATH . '/tests/helpers/autoload.php';
require_once BASE_PATH . '/tests/helpers/plugins/plugin_fixtures.php';
require_once BASE_PATH . '/tests/helpers/plugins/plugin_services.php';

use Xestify\core\Database;
use Xestify\exceptions\DatabaseException;

const PLUGIN_FIXTURE_VERSION = '1.0.0';

loadPluginTestEnv(BASE_PATH);

try {
    $pdo = Database::connection();
} catch (DatabaseException) {
    echo "[SKIP] PostgreSQL not reachable — all PluginRegistrationServiceTest cases skipped.\n";
    echo "       Configure backend/.env with valid DB_* vars and run migrations.\n";
    echo str_repeat('-', 40) . "\n";
    echo "Resultado: 0 passed, 0 failed (skipped)\n";
    exit(0);
}

echo str_repeat('-', 40) . "\n";

TestSuite::run('registerNew() activates the plugin immediately and fires onActivate()', function () use ($pdo): void {
    $slug = 'test_register_active_' . bin2hex(random_bytes(3));
    $lifecycle = <<<PHP
<?php
declare(strict_types=1);
namespace Xestify\plugins\\{$slug};
use PDO;
use Xestify\plugins\contracts\PluginLifecycleInterface;
final class Lifecycle implements PluginLifecycleInterface {
    public function __construct(private PDO \$pdo) {}
    public function onInstall(): void {}
    public function onActivate(): void { \$GLOBALS['register_activate_called'] = true; }
    public function onDeactivate(): void {}
}
PHP;
    $root = createPluginFixture([
        'name' => $slug,
        'label' => 'Register Active',
        'version' => PLUGIN_FIXTURE_VERSION,
        'type' => 'entity',
        'core_version' => PLUGIN_FIXTURE_VERSION,
    ], false, false, null, $lifecycle);

    try {
        $GLOBALS['register_activate_called'] = false;
        $service = buildPluginAdministrationService($root, $pdo);
        $inserted = $service->registerNew($slug, []);

        assertEquals('active', (string) ($inserted['status'] ?? ''), 'registerNew() must return an already-active plugin');
        assertTrue($GLOBALS['register_activate_called'] === true, 'onActivate() must fire during manual registration');

        $stmt = $pdo->prepare('SELECT status FROM plugins WHERE slug = :slug');
        $stmt->execute([':slug' => $slug]);
        assertEquals('active', (string) $stmt->fetchColumn(), 'the persisted row must be active, not just the returned array');
    } finally {
        cleanupPluginRecord($pdo, $slug);
        removePluginFixture($root);
    }
});

TestSuite::run('registerNew() with identity/field overrides still ends up active', function () use ($pdo): void {
    $slug = 'test_register_active_overrides_' . bin2hex(random_bytes(3));
    $root = createPluginFixture([
        'name' => $slug,
        'label' => 'Register Active Overrides',
        'version' => PLUGIN_FIXTURE_VERSION,
        'type' => 'entity',
        'core_version' => PLUGIN_FIXTURE_VERSION,
    ]);

    try {
        $service = buildPluginAdministrationService($root, $pdo);
        $inserted = $service->registerNew($slug, [
            'description' => 'Descripcion de prueba',
            'fields' => [
                ['key' => 'name', 'type' => 'string', 'label' => 'Name', 'required' => true, 'active' => true, 'summaryView' => true],
            ],
        ]);

        assertEquals('active', (string) ($inserted['status'] ?? ''), 'registerNew() with identity/config overrides must still return an active plugin');
    } finally {
        cleanupPluginRecord($pdo, $slug);
        removePluginFixture($root);
    }
});

TestSuite::run('syncAll() bulk auto-discovery still registers plugins as inactive', function () use ($pdo): void {
    $slug = 'test_register_bulk_sync_' . bin2hex(random_bytes(3));
    $root = createPluginFixture([
        'name' => $slug,
        'label' => 'Bulk Sync',
        'version' => PLUGIN_FIXTURE_VERSION,
        'type' => 'entity',
        'core_version' => PLUGIN_FIXTURE_VERSION,
    ]);

    try {
        $service = buildPluginAdministrationService($root, $pdo);
        $service->syncAll();

        $stmt = $pdo->prepare('SELECT status FROM plugins WHERE slug = :slug');
        $stmt->execute([':slug' => $slug]);
        assertEquals('inactive', (string) $stmt->fetchColumn(), 'bulk sync must not activate newly discovered plugins automatically');
    } finally {
        cleanupPluginRecord($pdo, $slug);
        removePluginFixture($root);
    }
});

echo str_repeat('-', 40) . "\n";
TestSuite::summary();
exit(TestSuite::exitCode());
