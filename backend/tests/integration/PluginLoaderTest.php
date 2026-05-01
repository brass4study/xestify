<?php

/**
 * PluginLoaderTest — Integration tests for PluginLoader.
 *
 * Uses temporary filesystem fixtures and a live PostgreSQL connection.
 * Cleans up all inserted test rows after each DB test.
 *
 * Run:
 *   php backend/tests/integration/PluginLoaderTest.php
 */

declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__, 2));

require_once BASE_PATH . '/tests/unit/helpers.php';
require_once BASE_PATH . '/src/exceptions/DatabaseException.php';
require_once BASE_PATH . '/src/exceptions/PluginException.php';
require_once BASE_PATH . '/src/core/Database.php';
require_once BASE_PATH . '/src/plugins/PluginLoader.php';

use Xestify\core\Database;
use Xestify\exceptions\DatabaseException;
use Xestify\exceptions\PluginException;
use Xestify\plugins\PluginLoader;

// ---------------------------------------------------------------------------
// Load .env
// ---------------------------------------------------------------------------

$envFile = BASE_PATH . '/.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
    foreach ($lines as $line) {
        if (str_starts_with(trim($line), '#') || !str_contains($line, '=')) {
            continue;
        }
        [$key, $value] = explode('=', $line, 2);
        $_ENV[trim($key)] = trim($value);
    }
}

// ---------------------------------------------------------------------------
// Connectivity probe
// ---------------------------------------------------------------------------

try {
    $pdo = Database::connection();
} catch (DatabaseException) {
    echo "[SKIP] PostgreSQL not reachable — all PluginLoaderTest cases skipped.\n";
    echo "       Configure backend/.env with valid DB_* vars and run migrations.\n";
    echo str_repeat('-', 40) . "\n";
    echo "Resultado: 0 passed, 0 failed (skipped)\n";
    exit(0);
}

// ---------------------------------------------------------------------------
// Fixture helpers
// ---------------------------------------------------------------------------

/**
 * Create a temporary plugin directory with a manifest.json (and optional Hooks.php).
 *
 * @param array $manifest       Manifest fields to write.
 * @param bool  $withHooks      Whether to create a Hooks.php file.
 * @param bool  $invalidJson    Write invalid JSON to manifest (for error tests).
 * @return string               Path to the created plugins root directory.
 */
function createPluginFixture(array $manifest, bool $withHooks = false, bool $invalidJson = false): string
{
    $root = sys_get_temp_dir() . '/xestify_plugin_test_' . bin2hex(random_bytes(4));
    $slug = $manifest['slug'] ?? 'test_plugin';
    $pluginDir = $root . '/' . $slug;

    mkdir($pluginDir, 0777, true);

    $jsonContent = $invalidJson ? '{bad json' : (string) json_encode($manifest, JSON_PRETTY_PRINT);
    file_put_contents($pluginDir . '/manifest.json', $jsonContent);

    if ($withHooks) {
        file_put_contents($pluginDir . '/Hooks.php', "<?php\n// Hooks loaded for test\n");
    }

    return $root;
}

/**
 * Remove a temporary plugins root directory recursively.
 */
function removeFixture(string $root): void
{
    if (!is_dir($root)) {
        return;
    }

    $items = scandir($root) ?: [];
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }

        $path = $root . '/' . $item;
        if (is_dir($path)) {
            removeFixture($path);
        } else {
            unlink($path);
        }
    }

    rmdir($root);
}

/**
 * Delete a test plugin row from plugins_registry.
 */
function cleanupPlugin(PDO $db, string $slug): void
{
    $stmt = $db->prepare('DELETE FROM plugins_registry WHERE plugin_slug = :slug');
    $stmt->execute([SLUG_BIND_PARAM => $slug]);
}

// ---------------------------------------------------------------------------
// Constants
// ---------------------------------------------------------------------------

define('SLUG_BIND_PARAM', ':slug');
define('SEMVER_1_0', '1.0.0');

// ---------------------------------------------------------------------------
// Tests
// ---------------------------------------------------------------------------

echo str_repeat('-', 40) . "\n";

TestSuite::run('discover() returns empty array when plugins dir does not exist', function (): void {
    $loader = new PluginLoader('/nonexistent/path/xyz', Database::connection());
    assertEquals([], $loader->discover(), 'Should return empty array');
});

TestSuite::run('discover() returns slug when valid plugin dir exists', function (): void {
    $manifest = ['slug' => 'test_disc', 'name' => 'Test', 'version' => SEMVER_1_0, 'type' => 'entity', 'core_version' => SEMVER_1_0];
    $root = createPluginFixture($manifest);

    try {
        $loader = new PluginLoader($root, Database::connection());
        $slugs = $loader->discover();
        assertTrue(in_array('test_disc', $slugs, true), 'discover() should return plugin slug');
    } finally {
        removeFixture($root);
    }
});

TestSuite::run('load() throws PluginException when manifest.json is missing', function (): void {
    $root = sys_get_temp_dir() . '/xestify_plugin_nomf_' . bin2hex(random_bytes(4));
    mkdir($root . '/no_manifest', 0777, true);

    try {
        $loader = new PluginLoader($root, Database::connection());
        $threw = false;

        try {
            $loader->load('no_manifest');
        } catch (PluginException) {
            $threw = true;
        }

        assertTrue($threw, 'Should throw PluginException for missing manifest');
    } finally {
        removeFixture($root);
    }
});

TestSuite::run('load() throws PluginException when manifest has invalid JSON', function (): void {
    $manifest = ['slug' => 'bad_json'];
    $root = createPluginFixture($manifest, false, true);

    try {
        $loader = new PluginLoader($root, Database::connection());
        $threw = false;

        try {
            $loader->load('bad_json');
        } catch (PluginException) {
            $threw = true;
        }

        assertTrue($threw, 'Should throw PluginException for invalid JSON');
    } finally {
        removeFixture($root);
    }
});

TestSuite::run('load() throws PluginException when plugin requires higher core version', function () use ($pdo): void {
    $manifest = ['slug' => 'future_plugin', 'name' => 'Future', 'version' => SEMVER_1_0, 'type' => 'entity', 'core_version' => '99.0.0'];
    $root = createPluginFixture($manifest);

    try {
        $loader = new PluginLoader($root, $pdo);
        $threw = false;

        try {
            $loader->load('future_plugin');
        } catch (PluginException) {
            $threw = true;
        }

        assertTrue($threw, 'Should throw PluginException for incompatible core version');
    } finally {
        removeFixture($root);
    }
});

TestSuite::run('load() registers new plugin in plugins_registry', function () use ($pdo): void {
    $slug = 'test_reg_' . bin2hex(random_bytes(3));
    $manifest = ['slug' => $slug, 'name' => 'Test Reg', 'version' => SEMVER_1_0, 'type' => 'entity', 'core_version' => SEMVER_1_0];
    $root = createPluginFixture($manifest);

    try {
        $loader = new PluginLoader($root, $pdo);
        $loaded = $loader->load($slug);

        assertEquals($slug, $loaded['slug'], 'Returned manifest slug should match');

        $stmt = $pdo->prepare('SELECT plugin_slug, version, status FROM plugins_registry WHERE plugin_slug = :slug');
        $stmt->execute([SLUG_BIND_PARAM => $slug]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        assertTrue($row !== false, 'Plugin should be inserted in plugins_registry');
        assertEquals($slug, (string) $row['plugin_slug'], 'plugin_slug should match');
        assertEquals(SEMVER_1_0, (string) $row['version'], 'version should match');
        assertEquals('inactive', (string) $row['status'], 'status should default to inactive');
    } finally {
        cleanupPlugin($pdo, $slug);
        removeFixture($root);
    }
});

TestSuite::run('load() updates version when plugin already registered', function () use ($pdo): void {
    $slug = 'test_upd_' . bin2hex(random_bytes(3));

    $stmt = $pdo->prepare(
        "INSERT INTO plugins_registry (plugin_slug, plugin_type, version, status)
         VALUES (:slug, 'entity', '0.9.0', 'inactive')"
    );
    $stmt->execute([SLUG_BIND_PARAM => $slug]);

    $manifest = ['slug' => $slug, 'name' => 'Test Upd', 'version' => '1.1.0', 'type' => 'entity', 'core_version' => SEMVER_1_0];
    $root = createPluginFixture($manifest);

    try {
        $loader = new PluginLoader($root, $pdo);
        $loader->load($slug);

        $check = $pdo->prepare('SELECT version FROM plugins_registry WHERE plugin_slug = :slug');
        $check->execute([SLUG_BIND_PARAM => $slug]);
        $row = $check->fetch(PDO::FETCH_ASSOC);

        assertEquals('1.1.0', (string) ($row['version'] ?? ''), 'Version should be updated to 1.1.0');
    } finally {
        cleanupPlugin($pdo, $slug);
        removeFixture($root);
    }
});

TestSuite::run('loadAll() loads all discovered plugins', function () use ($pdo): void {
    $slugA = 'test_all_a_' . bin2hex(random_bytes(3));
    $slugB = 'test_all_b_' . bin2hex(random_bytes(3));

    $root = sys_get_temp_dir() . '/xestify_all_' . bin2hex(random_bytes(4));
    mkdir($root, 0777, true);

    foreach ([$slugA, $slugB] as $s) {
        $dir = $root . '/' . $s;
        mkdir($dir, 0777, true);
        file_put_contents($dir . '/manifest.json', (string) json_encode([
            'slug' => $s, 'name' => $s, 'version' => SEMVER_1_0, 'type' => 'extension', 'core_version' => SEMVER_1_0,
        ]));
    }

    try {
        $loader = new PluginLoader($root, $pdo);
        $result = $loader->loadAll();

        assertTrue(isset($result[$slugA]), "loadAll() should include {$slugA}");
        assertTrue(isset($result[$slugB]), "loadAll() should include {$slugB}");
        assertEquals(2, count($result), 'loadAll() should return 2 plugins');
    } finally {
        cleanupPlugin($pdo, $slugA);
        cleanupPlugin($pdo, $slugB);
        removeFixture($root);
    }
});

// ---------------------------------------------------------------------------
// Summary
// ---------------------------------------------------------------------------

TestSuite::summary();
exit(TestSuite::exitCode());

