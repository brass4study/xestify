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
    echo "[SKIP] PostgreSQL not reachable — all PluginLifecycleInvokerTest cases skipped.\n";
    echo "       Configure backend/.env with valid DB_* vars and run migrations.\n";
    echo str_repeat('-', 40) . "\n";
    echo "Resultado: 0 passed, 0 failed (skipped)\n";
    exit(0);
}

echo str_repeat('-', 40) . "\n";

TestSuite::run('onInstall(), onActivate() and onDeactivate() call lifecycle methods', function () use ($pdo): void {
    $slug = 'lc' . bin2hex(random_bytes(4));
    $lifecycle = <<<PHP
<?php
declare(strict_types=1);
namespace Xestify\plugins\\{$slug};
use PDO;
use Xestify\plugins\PluginLifecycleInterface;
final class Lifecycle implements PluginLifecycleInterface {
    public function __construct(private PDO \$pdo) {}
    public function onInstall(): void { \$GLOBALS['lc_install'] = (\$GLOBALS['lc_install'] ?? 0) + 1; }
    public function onActivate(): void { \$GLOBALS['lc_activate'] = (\$GLOBALS['lc_activate'] ?? 0) + 1; }
    public function onDeactivate(): void { \$GLOBALS['lc_deactivate'] = (\$GLOBALS['lc_deactivate'] ?? 0) + 1; }
}
PHP;
    $root = createPluginFixture([
        'slug' => $slug,
        'name' => 'Lifecycle Plugin',
        'version' => '1.0.0',
        'type' => 'entity',
        'core_version' => '1.0.0',
    ], false, false, null, $lifecycle);

    try {
        $GLOBALS['lc_install'] = 0;
        $GLOBALS['lc_activate'] = 0;
        $GLOBALS['lc_deactivate'] = 0;
        $invoker = buildPluginLifecycleInvoker($root, $pdo);

        $invoker->onInstall($slug);
        $invoker->onActivate($slug);
        $invoker->onDeactivate($slug);

        assertEquals(1, $GLOBALS['lc_install'] ?? 0, 'onInstall should run once');
        assertEquals(1, $GLOBALS['lc_activate'] ?? 0, 'onActivate should run once');
        assertEquals(1, $GLOBALS['lc_deactivate'] ?? 0, 'onDeactivate should run once');
    } finally {
        removePluginFixture($root);
    }
});

TestSuite::run('onUpdate() is optional and receives context when implemented', function () use ($pdo): void {
    $slug = 'update' . bin2hex(random_bytes(4));
    $lifecycle = <<<PHP
<?php
declare(strict_types=1);
namespace Xestify\plugins\\{$slug};
use PDO;
use Xestify\plugins\PluginLifecycleInterface;
final class Lifecycle implements PluginLifecycleInterface {
    public function __construct(private PDO \$pdo) {}
    public function onInstall(): void {}
    public function onActivate(): void {}
    public function onDeactivate(): void {}
    public function onUpdate(array \$context): void { \$GLOBALS['lc_context_slug'] = \$context['slug'] ?? null; }
}
PHP;
    $root = createPluginFixture([
        'slug' => $slug,
        'name' => 'Lifecycle Update Plugin',
        'version' => '1.0.0',
        'type' => 'entity',
        'core_version' => '1.0.0',
    ], false, false, null, $lifecycle);

    try {
        $GLOBALS['lc_context_slug'] = null;
        $invoker = buildPluginLifecycleInvoker($root, $pdo);
        $invoker->onUpdate($slug, ['slug' => $slug]);
        assertEquals($slug, $GLOBALS['lc_context_slug'] ?? null, 'onUpdate must receive context');
    } finally {
        removePluginFixture($root);
    }
});

echo str_repeat('-', 40) . "\n";
TestSuite::summary();
exit(TestSuite::exitCode());
