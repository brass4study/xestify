<?php

declare(strict_types=1);

// ---------------------------------------------------------------------------
// Bootstrap
// ---------------------------------------------------------------------------
define('BASE_PATH', dirname(__DIR__, 2));
require_once BASE_PATH . '/src/bootstrap.php';
require_once __DIR__ . '/../unit/helpers.php';

use Xestify\plugins\PluginLoader;
use Xestify\core\Database;

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

/**
 * Create a minimal plugin fixture in a base dir.
 * Writes manifest.json and a Lifecycle.php that tracks calls via $GLOBALS.
 *
 * @param string $baseDir  Parent directory (e.g. sys_get_temp_dir())
 * @param string $slug     Plugin slug (must be a valid PHP identifier)
 */
function createLifecycleFixture(string $baseDir, string $slug): void
{
    $dir = $baseDir . '/' . $slug;
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }

    file_put_contents($dir . '/manifest.json', json_encode([
        'slug'         => $slug,
        'name'         => 'Test Lifecycle Plugin',
        'version'      => '1.0.0',
        'type'         => 'entity',
        'core_version' => '1.0.0',
    ]));

    // Write a Lifecycle.php whose methods increment $GLOBALS counters
    $ns = 'Xestify\\plugins\\' . $slug;
    $src = <<<PHP
<?php
declare(strict_types=1);
namespace {$ns};
use PDO;
use Xestify\plugins\PluginLifecycleInterface;
final class Lifecycle implements PluginLifecycleInterface {
    public function __construct(private PDO \$pdo) {}
    public function onInstall(): void {
        \$GLOBALS['lc_install'] = (\$GLOBALS['lc_install'] ?? 0) + 1;
    }
    public function onActivate(): void {
        \$GLOBALS['lc_activate'] = (\$GLOBALS['lc_activate'] ?? 0) + 1;
    }
    public function onDeactivate(): void {
        \$GLOBALS['lc_deactivate'] = (\$GLOBALS['lc_deactivate'] ?? 0) + 1;
    }
}
PHP;
    file_put_contents($dir . '/Lifecycle.php', $src);
}

function removeLifecycleFixture(string $baseDir, string $slug): void
{
    $dir = $baseDir . '/' . $slug;
    if (!is_dir($dir)) {
        return;
    }
    foreach (scandir($dir) ?: [] as $f) {
        if ($f !== '.' && $f !== '..') {
            unlink($dir . '/' . $f);
        }
    }
    rmdir($dir);
}

function cleanupLifecyclePlugin(PDO $pdo, string $slug): void
{
    $pdo->prepare('DELETE FROM plugins_registry WHERE plugin_slug = :slug')
        ->execute([':slug' => $slug]);
}

// ---------------------------------------------------------------------------
// Setup
// ---------------------------------------------------------------------------
$pdo     = Database::connection();
$tmpDir  = sys_get_temp_dir() . '/xestify_lc_' . bin2hex(random_bytes(4));
$slug    = 'lc' . bin2hex(random_bytes(4)); // valid PHP identifier (hex only)
mkdir($tmpDir, 0777, true);

createLifecycleFixture($tmpDir, $slug);

// ---------------------------------------------------------------------------
// Tests
// ---------------------------------------------------------------------------

TestSuite::run('onInstall llamado la primera vez que se carga el plugin', function () use ($pdo, $tmpDir, $slug): void {
    $GLOBALS['lc_install'] = 0;
    $loader = new PluginLoader($tmpDir, $pdo);
    $loader->load($slug);
    assert($GLOBALS['lc_install'] === 1, 'onInstall must be called exactly once on first load');
});

TestSuite::run('onInstall NO llamado en cargas posteriores (plugin ya registrado)', function () use ($pdo, $tmpDir, $slug): void {
    $GLOBALS['lc_install'] = 0;
    $loader = new PluginLoader($tmpDir, $pdo);
    $loader->load($slug); // plugin already in DB
    assert($GLOBALS['lc_install'] === 0, 'onInstall must not be called when plugin already exists');
});

TestSuite::run('activate() actualiza status a active en plugins_registry', function () use ($pdo, $tmpDir, $slug): void {
    $loader = new PluginLoader($tmpDir, $pdo);
    $loader->activate($slug);

    $stmt = $pdo->prepare('SELECT status FROM plugins_registry WHERE plugin_slug = :slug');
    $stmt->execute([':slug' => $slug]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    assert($row !== false, 'plugin must be in registry');
    assert($row['status'] === 'active', 'status must be active after activate()');
});

TestSuite::run('activate() llama a onActivate()', function () use ($pdo, $tmpDir, $slug): void {
    $GLOBALS['lc_activate'] = 0;
    $loader = new PluginLoader($tmpDir, $pdo);
    $loader->activate($slug);
    assert($GLOBALS['lc_activate'] === 1, 'onActivate must be called once');
});

TestSuite::run('deactivate() actualiza status a inactive en plugins_registry', function () use ($pdo, $tmpDir, $slug): void {
    $loader = new PluginLoader($tmpDir, $pdo);
    $loader->deactivate($slug);

    $stmt = $pdo->prepare('SELECT status FROM plugins_registry WHERE plugin_slug = :slug');
    $stmt->execute([':slug' => $slug]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    assert($row !== false, 'plugin must be in registry');
    assert($row['status'] === 'inactive', 'status must be inactive after deactivate()');
});

TestSuite::run('deactivate() llama a onDeactivate()', function () use ($pdo, $tmpDir, $slug): void {
    $GLOBALS['lc_deactivate'] = 0;
    $loader = new PluginLoader($tmpDir, $pdo);
    $loader->deactivate($slug);
    assert($GLOBALS['lc_deactivate'] === 1, 'onDeactivate must be called once');
});

TestSuite::run('load() funciona sin Lifecycle.php (plugin sin ciclo de vida)', function () use ($pdo, $tmpDir): void {
    $noLcSlug = 'nolc' . bin2hex(random_bytes(3));
    $dir = $tmpDir . '/' . $noLcSlug;
    mkdir($dir, 0777, true);
    file_put_contents($dir . '/manifest.json', json_encode([
        'slug'         => $noLcSlug,
        'name'         => 'No Lifecycle Plugin',
        'version'      => '1.0.0',
        'type'         => 'entity',
        'core_version' => '1.0.0',
    ]));

    try {
        $loader   = new PluginLoader($tmpDir, $pdo);
        $manifest = $loader->load($noLcSlug);
        assert($manifest['slug'] === $noLcSlug, 'load() must return manifest even without Lifecycle.php');
    } finally {
        cleanupLifecyclePlugin($pdo, $noLcSlug);
        array_map('unlink', glob($dir . '/*') ?: []);
        rmdir($dir);
    }
});

TestSuite::run('activate() y deactivate() en ciclo completo', function () use ($pdo, $tmpDir, $slug): void {
    $GLOBALS['lc_activate']   = 0;
    $GLOBALS['lc_deactivate'] = 0;
    $loader = new PluginLoader($tmpDir, $pdo);

    $loader->activate($slug);
    $loader->deactivate($slug);
    $loader->activate($slug);

    assert($GLOBALS['lc_activate'] === 2, 'onActivate must be called twice');
    assert($GLOBALS['lc_deactivate'] === 1, 'onDeactivate must be called once');

    $stmt = $pdo->prepare('SELECT status FROM plugins_registry WHERE plugin_slug = :slug');
    $stmt->execute([':slug' => $slug]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    assert(($row['status'] ?? '') === 'active', 'final status must be active');
});

// ---------------------------------------------------------------------------
// Cleanup
// ---------------------------------------------------------------------------
cleanupLifecyclePlugin($pdo, $slug);
removeLifecycleFixture($tmpDir, $slug);
rmdir($tmpDir);

// ---------------------------------------------------------------------------
TestSuite::summary();
exit(TestSuite::exitCode());
