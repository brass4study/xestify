<?php

declare(strict_types=1);

// ---------------------------------------------------------------------------
// Bootstrap
// ---------------------------------------------------------------------------
define('BASE_PATH', dirname(__DIR__, 2));
require_once BASE_PATH . '/src/bootstrap.php';
require_once __DIR__ . '/../unit/helpers.php';

use Xestify\plugins\PluginLoader;
use Xestify\exceptions\PluginException;
use Xestify\core\Database;

define('DEP_TEST_VERSION', '1.0.0');

// ---------------------------------------------------------------------------
// Fixture helpers
// ---------------------------------------------------------------------------

/**
 * Create a minimal plugin manifest in a temp directory.
 *
 * @param string   $baseDir
 * @param string   $slug
 * @param string   $version
 * @param array[]  $requires  Array of ['slug' => ..., 'version' => ...] deps
 */
function createDepFixture(string $baseDir, string $slug, string $version = DEP_TEST_VERSION, array $requires = []): void
{
    $dir = $baseDir . '/' . $slug;
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }

    $manifest = [
        'slug'         => $slug,
        'name'         => 'Dep Test Plugin ' . $slug,
        'version'      => $version,
        'type'         => 'entity',
        'core_version' => DEP_TEST_VERSION,
    ];

    if ($requires !== []) {
        $manifest['requires'] = $requires;
    }

    file_put_contents($dir . '/manifest.json', json_encode($manifest));
}

function removeDepFixture(string $baseDir, string $slug): void
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

function cleanupDepPlugin(PDO $pdo, string $slug): void
{
    $pdo->prepare('DELETE FROM plugins_registry WHERE plugin_slug = :slug')
        ->execute([':slug' => $slug]);
}

// ---------------------------------------------------------------------------
// Setup
// ---------------------------------------------------------------------------
$pdo    = Database::connection();
$tmpDir = sys_get_temp_dir() . '/xestify_dep_' . bin2hex(random_bytes(4));
mkdir($tmpDir, 0777, true);

// Slugs — hex-only for valid PHP identifiers where needed
$slugA = 'depa' . bin2hex(random_bytes(3));
$slugB = 'depb' . bin2hex(random_bytes(3));

// ---------------------------------------------------------------------------
// Tests
// ---------------------------------------------------------------------------

TestSuite::run('load() con requires vacío carga el plugin sin error', function () use ($pdo, $tmpDir, $slugA): void {
    createDepFixture($tmpDir, $slugA, DEP_TEST_VERSION, []);
    try {
        $loader = new PluginLoader($tmpDir, $pdo);
        $manifest = $loader->load($slugA);
        assert($manifest['slug'] === $slugA, 'manifest slug must match');
    } finally {
        cleanupDepPlugin($pdo, $slugA);
        removeDepFixture($tmpDir, $slugA);
    }
});

TestSuite::run('load() falla si plugin requerido no está instalado', function () use ($pdo, $tmpDir, $slugA, $slugB): void {
    // slugB requires slugA which is not registered
    createDepFixture($tmpDir, $slugB, DEP_TEST_VERSION, [['slug' => $slugA, 'version' => DEP_TEST_VERSION]]);
    $caught = false;
    try {
        $loader = new PluginLoader($tmpDir, $pdo);
        $loader->load($slugB);
    } catch (PluginException $e) {
        $caught = true;
        assert(
            str_contains($e->getMessage(), $slugA),
            'exception must mention missing dependency slug'
        );
    } finally {
        removeDepFixture($tmpDir, $slugB);
        cleanupDepPlugin($pdo, $slugB);
    }
    assert($caught, 'PluginException must be thrown when dependency is missing');
});

TestSuite::run('load() carga plugin correctamente cuando dependencia ya está instalada', function () use ($pdo, $tmpDir, $slugA, $slugB): void {
    // Register slugA first
    createDepFixture($tmpDir, $slugA, DEP_TEST_VERSION, []);
    $loaderA = new PluginLoader($tmpDir, $pdo);
    $loaderA->load($slugA);

    // Now slugB requires slugA
    createDepFixture($tmpDir, $slugB, DEP_TEST_VERSION, [['slug' => $slugA, 'version' => DEP_TEST_VERSION]]);
    try {
        $loaderB = new PluginLoader($tmpDir, $pdo);
        $manifest = $loaderB->load($slugB);
        assert($manifest['slug'] === $slugB, 'manifest slug must match');
    } finally {
        cleanupDepPlugin($pdo, $slugA);
        cleanupDepPlugin($pdo, $slugB);
        removeDepFixture($tmpDir, $slugA);
        removeDepFixture($tmpDir, $slugB);
    }
});

TestSuite::run('load() falla si versión de dependencia instalada es menor a la requerida', function () use ($pdo, $tmpDir, $slugA, $slugB): void {
    // Install slugA with version 1.0.0
    createDepFixture($tmpDir, $slugA, DEP_TEST_VERSION, []);
    $loaderA = new PluginLoader($tmpDir, $pdo);
    $loaderA->load($slugA);

    // slugB requires slugA >= 2.0.0
    createDepFixture($tmpDir, $slugB, DEP_TEST_VERSION, [['slug' => $slugA, 'version' => '2.0.0']]);
    $caught = false;
    try {
        $loaderB = new PluginLoader($tmpDir, $pdo);
        $loaderB->load($slugB);
    } catch (PluginException $e) {
        $caught = true;
        assert(
            str_contains($e->getMessage(), '2.0.0'),
            'exception must mention required version'
        );
    } finally {
        cleanupDepPlugin($pdo, $slugA);
        cleanupDepPlugin($pdo, $slugB);
        removeDepFixture($tmpDir, $slugA);
        removeDepFixture($tmpDir, $slugB);
    }
    assert($caught, 'PluginException must be thrown when dependency version is too low');
});

TestSuite::run('load() falla si entrada en requires no tiene campo slug', function () use ($pdo, $tmpDir, $slugA): void {
    createDepFixture($tmpDir, $slugA, DEP_TEST_VERSION);
    // Manually write a manifest with a bad requires entry
    $dir = $tmpDir . '/' . $slugA;
    file_put_contents($dir . '/manifest.json', json_encode([
        'slug'         => $slugA,
        'name'         => 'Bad Dep Plugin',
        'version'      => DEP_TEST_VERSION,
        'type'         => 'entity',
        'core_version' => DEP_TEST_VERSION,
        'requires'     => [['version' => DEP_TEST_VERSION]], // missing 'slug'
    ]));
    $caught = false;
    try {
        $loader = new PluginLoader($tmpDir, $pdo);
        $loader->load($slugA);
    } catch (PluginException $e) {
        $caught = true;
        assert(
            str_contains($e->getMessage(), 'invalid'),
            'exception must mention invalid requires entry'
        );
    } finally {
        cleanupDepPlugin($pdo, $slugA);
        removeDepFixture($tmpDir, $slugA);
    }
    assert($caught, 'PluginException must be thrown for malformed requires entry');
});

TestSuite::run('load() acepta requires sin campo version (usa 0.0.0 como mínimo)', function () use ($pdo, $tmpDir, $slugA, $slugB): void {
    // Install slugA with version 1.0.0
    createDepFixture($tmpDir, $slugA, DEP_TEST_VERSION, []);
    $loaderA = new PluginLoader($tmpDir, $pdo);
    $loaderA->load($slugA);

    // slugB requires slugA without specifying version (any version is fine)
    createDepFixture($tmpDir, $slugB, DEP_TEST_VERSION, [['slug' => $slugA]]); // no 'version' key
    try {
        $loaderB = new PluginLoader($tmpDir, $pdo);
        $manifest = $loaderB->load($slugB);
        assert($manifest['slug'] === $slugB, 'must load successfully');
    } finally {
        cleanupDepPlugin($pdo, $slugA);
        cleanupDepPlugin($pdo, $slugB);
        removeDepFixture($tmpDir, $slugA);
        removeDepFixture($tmpDir, $slugB);
    }
});

// ---------------------------------------------------------------------------
// Cleanup
// ---------------------------------------------------------------------------
if (is_dir($tmpDir)) {
    rmdir($tmpDir);
}

// ---------------------------------------------------------------------------
TestSuite::summary();
exit(TestSuite::exitCode());
