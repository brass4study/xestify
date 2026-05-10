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
require_once BASE_PATH . '/src/plugins/PluginLifecycleInterface.php';
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
 * @param array       $manifest        Manifest fields to write.
 * @param bool        $withHooks       Whether to create a Hooks.php file.
 * @param bool        $invalidJson     Write invalid JSON to manifest (for error tests).
 * @param array|null  $schema          Optional schema payload override for entity plugins.
 * @param string|null $lifecycleSource Optional Lifecycle.php contents.
 * @return string               Path to the created plugins root directory.
 */
function createPluginFixture(
    array $manifest,
    bool $withHooks = false,
    bool $invalidJson = false,
    ?array $schema = null,
    ?string $lifecycleSource = null
): string
{
    $root = sys_get_temp_dir() . '/xestify_plugin_test_' . bin2hex(random_bytes(4));
    $slug = $manifest['slug'] ?? 'test_plugin';
    $pluginDir = $root . '/' . $slug;

    mkdir($pluginDir, 0777, true);

    $jsonContent = $invalidJson ? '{bad json' : (string) json_encode($manifest, JSON_PRETTY_PRINT);
    file_put_contents($pluginDir . MANIFEST_FILE_PATH, $jsonContent);

    if (($manifest['type'] ?? '') === 'entity' && !$invalidJson) {
        $schema ??= [
            'entity' => $slug,
            'version' => $manifest['version'] ?? SEMVER_1_0,
            'identities' => [
                'id' => ['type' => 'uuid', 'auto_generated' => true, 'editable' => false],
            ],
            'fields' => [
                'name' => ['type' => 'string', 'required' => true, 'label' => 'Name'],
            ],
            'custom_fields' => [],
            'relations' => [],
        ];
        file_put_contents($pluginDir . '/schema.json', json_encode($schema, JSON_PRETTY_PRINT));
    }

    if ($withHooks) {
        file_put_contents($pluginDir . '/Hooks.php', "<?php\n// Hooks loaded for test\n");
    }

    if ($lifecycleSource !== null) {
        file_put_contents($pluginDir . '/Lifecycle.php', $lifecycleSource);
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
 * Delete a test plugin row from plugins.
 */
function cleanupPlugin(PDO $db, string $slug): void
{
    $history = $db->prepare('DELETE FROM plugin_update_history WHERE slug = :slug');
    $history->execute([SLUG_BIND_PARAM => $slug]);

    $stmt = $db->prepare('DELETE FROM plugins WHERE slug = :slug');
    $stmt->execute([SLUG_BIND_PARAM => $slug]);
}

// ---------------------------------------------------------------------------
// Constants
// ---------------------------------------------------------------------------

define('SLUG_BIND_PARAM', ':slug');
define('SEMVER_1_0', '1.0.0');
define('SEMVER_1_1', '1.1.0');
define('SEMVER_2_0', '2.0.0');
define('MANIFEST_FILE_PATH', '/manifest.json');

// ---------------------------------------------------------------------------
// Tests
// ---------------------------------------------------------------------------

echo str_repeat('-', 40) . "\n";

TestSuite::run('discover() returns empty array when plugins dir does not exist', function (): void {
    $loader = new PluginLoader('/nonexistent/path/xyz', Database::connection());
    assertEquals([], $loader->discover(), 'Should return empty array');
});

TestSuite::run('discover() returns slug when valid plugin dir exists', function (): void {
    $manifest = [
        'slug' => 'test_disc',
        'name' => 'Test',
        'version' => SEMVER_1_0,
        'type' => 'entity',
        'core_version' => SEMVER_1_0,
    ];
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
    $manifest = [
        'slug' => 'future_plugin',
        'name' => 'Future',
        'version' => SEMVER_1_0,
        'type' => 'entity',
        'core_version' => '99.0.0',
    ];
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

TestSuite::run('load() registers new plugin in plugins', function () use ($pdo): void {
    $slug = 'test_reg_' . bin2hex(random_bytes(3));
    $manifest = [
        'slug' => $slug,
        'name' => 'Test Reg',
        'version' => SEMVER_1_0,
        'type' => 'entity',
        'core_version' => SEMVER_1_0,
    ];
    $root = createPluginFixture($manifest);

    try {
        $loader = new PluginLoader($root, $pdo);
        $loaded = $loader->load($slug);

        assertEquals($slug, $loaded['slug'], 'Returned manifest slug should match');

        $stmt = $pdo->prepare('SELECT slug, version, status, schema_json FROM plugins WHERE slug = :slug');
        $stmt->execute([SLUG_BIND_PARAM => $slug]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        assertTrue($row !== false, 'Plugin should be inserted in plugins');
        assertEquals($slug, (string) $row['slug'], 'slug should match');
        assertEquals(SEMVER_1_0, (string) $row['version'], 'version should match');
        assertEquals('inactive', (string) $row['status'], 'status should default to inactive');
        assertTrue($row['schema_json'] !== null, 'entity schema_json should be persisted');
    } finally {
        cleanupPlugin($pdo, $slug);
        removeFixture($root);
    }
});

TestSuite::run('load() throws PluginException when entity plugin lacks schema.json', function () use ($pdo): void {
    $slug = 'test_no_schema_' . bin2hex(random_bytes(3));
    $root = sys_get_temp_dir() . '/xestify_plugin_test_' . bin2hex(random_bytes(4));
    $pluginDir = $root . '/' . $slug;
    mkdir($pluginDir, 0777, true);
    file_put_contents($pluginDir . MANIFEST_FILE_PATH, (string) json_encode([
        'slug' => $slug,
        'name' => 'No Schema',
        'version' => SEMVER_1_0,
        'type' => 'entity',
        'core_version' => SEMVER_1_0,
    ]));

    try {
        $loader = new PluginLoader($root, $pdo);
        $threw = false;
        try {
            $loader->load($slug);
        } catch (PluginException) {
            $threw = true;
        }

        assertTrue($threw, 'Entity plugins without schema.json must be rejected');
    } finally {
        cleanupPlugin($pdo, $slug);
        removeFixture($root);
    }
});

TestSuite::run('load() preserves installed version when plugin already registered', function () use ($pdo): void {
    $slug = 'test_upd_' . bin2hex(random_bytes(3));

    $stmt = $pdo->prepare(
        "INSERT INTO plugins (slug, plugin_type, version, status)
         VALUES (:slug, 'entity', '0.9.0', 'inactive')"
    );
    $stmt->execute([SLUG_BIND_PARAM => $slug]);

    $manifest = [
        'slug' => $slug,
        'name' => 'Test Upd',
        'version' => SEMVER_1_1,
        'type' => 'entity',
        'core_version' => SEMVER_1_0,
    ];
    $root = createPluginFixture($manifest);

    try {
        $loader = new PluginLoader($root, $pdo);
        $loader->load($slug);

        $check = $pdo->prepare('SELECT version FROM plugins WHERE slug = :slug');
        $check->execute([SLUG_BIND_PARAM => $slug]);
        $row = $check->fetch(PDO::FETCH_ASSOC);

        assertEquals('0.9.0', (string) ($row['version'] ?? ''), 'Installed version should be preserved');
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
        file_put_contents($dir . MANIFEST_FILE_PATH, (string) json_encode([
            'slug' => $s,
            'name' => $s,
            'version' => SEMVER_1_0,
            'type' => 'extension',
            'core_version' => SEMVER_1_0,
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

TestSuite::run('syncAll() registers new plugin and returns summary', function () use ($pdo): void {
    $slug = 'test_sync_new_' . bin2hex(random_bytes(3));
    $manifest = [
        'slug' => $slug,
        'name' => 'Sync New',
        'version' => SEMVER_1_0,
        'type' => 'entity',
        'core_version' => SEMVER_1_0,
    ];
    $root = createPluginFixture($manifest);

    try {
        $loader = new PluginLoader($root, $pdo);
        $result = $loader->syncAll();

        assertEquals(1, $result['summary']['discovered'], 'Should discover one plugin');
        assertEquals(1, $result['summary']['registered'], 'Should register one plugin');
        assertEquals('registered', $result['plugins'][$slug]['result'], 'Plugin should be registered');

        $stmt = $pdo->prepare('SELECT version, schema_json FROM plugins WHERE slug = :slug');
        $stmt->execute([SLUG_BIND_PARAM => $slug]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        assertTrue($row !== false, 'Plugin must exist after sync');
        assertEquals(SEMVER_1_0, (string) $row['version'], 'Installed version should match manifest');
        assertTrue($row['schema_json'] !== null, 'Entity schema should be persisted on first sync');
    } finally {
        cleanupPlugin($pdo, $slug);
        removeFixture($root);
    }
});

TestSuite::run('syncAll() preserves installed runtime for existing outdated plugin', function () use ($pdo): void {
    $slug = 'test_sync_old_' . bin2hex(random_bytes(3));
    $pdo->prepare(
        "INSERT INTO plugins (slug, name, plugin_type, version, status, schema_version, schema_json)
         VALUES (:slug, :name, 'entity', '1.0.0', 'inactive', 4, :schema::jsonb)"
    )->execute([
        ':slug' => $slug,
        ':name' => 'Old Name',
        ':schema' => json_encode([
            'entity' => $slug,
            'fields' => [
                'name' => ['type' => 'string', 'required' => true, 'label' => 'Old Label'],
            ],
            'custom_fields' => [],
            'relations' => [],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    ]);

    $manifest = [
        'slug' => $slug,
        'name' => 'New Name',
        'version' => SEMVER_2_0,
        'type' => 'entity',
        'core_version' => SEMVER_1_0,
    ];
    $schema = [
        'entity' => $slug,
        'version' => SEMVER_2_0,
        'fields' => [
            'name' => ['type' => 'string', 'required' => true, 'label' => 'New Label'],
            'email' => ['type' => 'email', 'required' => false, 'label' => 'Email'],
        ],
        'custom_fields' => [],
        'relations' => [],
    ];
    $root = createPluginFixture($manifest, false, false, $schema);

    try {
        $loader = new PluginLoader($root, $pdo);
        $result = $loader->syncAll();

        assertEquals('outdated', $result['plugins'][$slug]['result'], 'Plugin should be reported as outdated');

        $stmt = $pdo->prepare('SELECT name, version, schema_version, schema_json FROM plugins WHERE slug = :slug');
        $stmt->execute([SLUG_BIND_PARAM => $slug]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        assertTrue($row !== false, 'Plugin must still exist after sync');
        assertEquals('New Name', (string) $row['name'], 'Safe metadata refresh should update name');
        assertEquals(SEMVER_1_0, (string) $row['version'], 'Installed version must be preserved');
        assertEquals('4', (string) $row['schema_version'], 'schema_version must be preserved');

        $decoded = json_decode((string) $row['schema_json'], true);
        assertEquals('Old Label', (string) ($decoded['fields']['name']['label'] ?? ''), 'Schema must not be consumed');
        assertTrue(!isset($decoded['fields']['email']), 'New disk fields must not be applied during sync');
    } finally {
        cleanupPlugin($pdo, $slug);
        removeFixture($root);
    }
});

TestSuite::run('syncAll() reports invalid manifest without aborting batch', function () use ($pdo): void {
    $goodSlug = 'test_sync_good_' . bin2hex(random_bytes(3));
    $badSlug = 'test_sync_bad_' . bin2hex(random_bytes(3));
    $root = sys_get_temp_dir() . '/xestify_sync_batch_' . bin2hex(random_bytes(4));
    mkdir($root, 0777, true);

    $goodDir = $root . '/' . $goodSlug;
    mkdir($goodDir, 0777, true);
    file_put_contents($goodDir . MANIFEST_FILE_PATH, (string) json_encode([
        'slug' => $goodSlug,
        'name' => 'Good',
        'version' => SEMVER_1_0,
        'type' => 'extension',
        'core_version' => SEMVER_1_0,
    ]));

    $badDir = $root . '/' . $badSlug;
    mkdir($badDir, 0777, true);
    file_put_contents($badDir . MANIFEST_FILE_PATH, '{invalid');

    try {
        $loader = new PluginLoader($root, $pdo);
        $result = $loader->syncAll();

        assertEquals(2, $result['summary']['discovered'], 'Should discover both plugin directories');
        assertEquals(1, $result['summary']['registered'], 'Should register the valid plugin');
        assertEquals(1, $result['summary']['errors'], 'Should report one sync error');
        assertEquals('registered', $result['plugins'][$goodSlug]['result'], 'Valid plugin should be registered');
        assertEquals('error', $result['plugins'][$badSlug]['result'], 'Invalid plugin should be reported as error');

        $stmt = $pdo->prepare('SELECT slug FROM plugins WHERE slug = :slug');
        $stmt->execute([SLUG_BIND_PARAM => $goodSlug]);
        assertTrue($stmt->fetch(PDO::FETCH_ASSOC) !== false, 'Valid plugin should still be inserted');
    } finally {
        cleanupPlugin($pdo, $goodSlug);
        cleanupPlugin($pdo, $badSlug);
        removeFixture($root);
    }
});

TestSuite::run('getOutdated() returns plugin when disk version is greater', function () use ($pdo): void {
    $slug = 'test_outdated_' . bin2hex(random_bytes(3));
    $stmt = $pdo->prepare(
        "INSERT INTO plugins (slug, plugin_type, version, status)
         VALUES (:slug, 'entity', :version, 'inactive')"
    );
    $stmt->execute([SLUG_BIND_PARAM => $slug, ':version' => SEMVER_1_0]);

    $manifest = [
        'slug' => $slug,
        'name' => 'Test Outdated',
        'version' => SEMVER_2_0,
        'type' => 'entity',
        'core_version' => SEMVER_1_0,
    ];
    $root = createPluginFixture($manifest);

    try {
        $loader = new PluginLoader($root, $pdo);
        $outdated = $loader->getOutdated();

        $found = false;
        foreach ($outdated as $item) {
            if ($item['slug'] === $slug) {
                $found = true;
            }
        }

        assertTrue($found, 'Should report plugin when disk version is greater');
    } finally {
        cleanupPlugin($pdo, $slug);
        removeFixture($root);
    }
});

TestSuite::run('update() upgrades plugin version without schema changes and stores snapshot', function () use ($pdo): void {
    $slug = 'test_update_ver_' . bin2hex(random_bytes(3));
    $pdo->prepare(
        "INSERT INTO plugins (slug, name, plugin_type, version, status, schema_version, schema_json)
         VALUES (:slug, 'Versioned Plugin', 'entity', '1.0.0', 'inactive', 1, :schema::jsonb)"
    )->execute([
        ':slug' => $slug,
        ':schema' => json_encode([
            'entity' => $slug,
            'version' => SEMVER_1_0,
            'fields' => [
                'name' => ['type' => 'string', 'required' => true, 'label' => 'Name'],
            ],
            'custom_fields' => [],
            'relations' => [],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    ]);

    $manifest = [
        'slug' => $slug,
        'name' => 'Versioned Plugin',
        'version' => SEMVER_2_0,
        'type' => 'entity',
        'core_version' => SEMVER_1_0,
    ];
    $schema = [
        'entity' => $slug,
        'version' => SEMVER_2_0,
        'fields' => [
            'name' => ['type' => 'string', 'required' => true, 'label' => 'Name'],
        ],
        'custom_fields' => [],
        'relations' => [],
    ];
    $root = createPluginFixture($manifest, false, false, $schema);

    try {
        $loader = new PluginLoader($root, $pdo);
        $result = $loader->update($slug);

        assertEquals('1.0.0', $result['update']['from_version'], 'Should report old version');
        assertEquals(SEMVER_2_0, $result['update']['to_version'], 'Should report new version');
        assertTrue($result['update']['schema_changed'] === false, 'Schema should remain unchanged');

        $stmt = $pdo->prepare('SELECT version, schema_version FROM plugins WHERE slug = :slug');
        $stmt->execute([SLUG_BIND_PARAM => $slug]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        assertEquals(SEMVER_2_0, (string) ($row['version'] ?? ''), 'Installed version should be upgraded');
        assertEquals('1', (string) ($row['schema_version'] ?? ''), 'schema_version should not change');

        $history = $pdo->prepare(
            'SELECT COUNT(*) AS cnt FROM plugin_update_history WHERE slug = :slug AND target_version = :target'
        );
        $history->execute([SLUG_BIND_PARAM => $slug, ':target' => SEMVER_2_0]);
        $historyRow = $history->fetch(PDO::FETCH_ASSOC);
        assertEquals('1', (string) ($historyRow['cnt'] ?? '0'), 'Successful update should create one snapshot');
    } finally {
        cleanupPlugin($pdo, $slug);
        removeFixture($root);
    }
});

TestSuite::run('update() merges additive schema changes and increments schema version', function () use ($pdo): void {
    $slug = 'test_update_schema_' . bin2hex(random_bytes(3));
    $pdo->prepare(
        "INSERT INTO plugins (slug, name, plugin_type, version, status, schema_version, schema_json)
         VALUES (:slug, 'Schema Plugin', 'entity', '1.0.0', 'inactive', 2, :schema::jsonb)"
    )->execute([
        ':slug' => $slug,
        ':schema' => json_encode([
            'entity' => $slug,
            'version' => SEMVER_1_0,
            'identities' => [
                'id' => ['type' => 'uuid', 'auto_generated' => true, 'editable' => false],
            ],
            'fields' => [
                'name' => ['type' => 'string', 'required' => true, 'label' => 'Name'],
            ],
            'custom_fields' => [],
            'relations' => [],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    ]);

    $manifest = [
        'slug' => $slug,
        'name' => 'Schema Plugin',
        'version' => SEMVER_1_1,
        'type' => 'entity',
        'core_version' => SEMVER_1_0,
    ];
    $schema = [
        'entity' => $slug,
        'version' => SEMVER_1_1,
        'identities' => [
            'id' => ['type' => 'uuid', 'auto_generated' => true, 'editable' => false],
        ],
        'fields' => [
            'name' => ['type' => 'string', 'required' => true, 'label' => 'Name'],
            'email' => ['type' => 'email', 'required' => false, 'label' => 'Email'],
        ],
        'custom_fields' => [
            ['key' => 'phone', 'type' => 'string', 'required' => false, 'label' => 'Phone'],
        ],
        'relations' => [],
    ];
    $root = createPluginFixture($manifest, false, false, $schema);

    try {
        $loader = new PluginLoader($root, $pdo);
        $result = $loader->update($slug);

        assertTrue($result['update']['schema_changed'] === true, 'Schema should change on additive update');
        assertTrue(in_array('email', $result['update']['diff']['fields']['added'], true), 'email field should be added');
        assertTrue(
            in_array('phone', $result['update']['diff']['custom_fields']['added'], true),
            'phone custom field should be added'
        );

        $stmt = $pdo->prepare('SELECT version, schema_version, schema_json FROM plugins WHERE slug = :slug');
        $stmt->execute([SLUG_BIND_PARAM => $slug]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $decoded = json_decode((string) ($row['schema_json'] ?? '{}'), true);

        assertEquals(SEMVER_1_1, (string) ($row['version'] ?? ''), 'Version should be updated');
        assertEquals('3', (string) ($row['schema_version'] ?? ''), 'schema_version should increment');
        assertTrue(isset($decoded['fields']['email']), 'New field should be merged into live schema');
        assertEquals('Phone', (string) ($decoded['custom_fields'][0]['label'] ?? ''), 'New custom field should be merged');
    } finally {
        cleanupPlugin($pdo, $slug);
        removeFixture($root);
    }
});

TestSuite::run('update() fails when disk version is not greater', function () use ($pdo): void {
    $slug = 'test_update_same_' . bin2hex(random_bytes(3));
    $pdo->prepare(
        "INSERT INTO plugins (slug, name, plugin_type, version, status, schema_version, schema_json)
         VALUES (:slug, 'Same Version', 'entity', :version, 'inactive', 1, :schema::jsonb)"
    )->execute([
        ':slug' => $slug,
        ':version' => SEMVER_1_0,
        ':schema' => json_encode([
            'entity' => $slug,
            'fields' => [
                'name' => ['type' => 'string', 'required' => true, 'label' => 'Name'],
            ],
            'custom_fields' => [],
            'relations' => [],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    ]);

    $manifest = [
        'slug' => $slug,
        'name' => 'Same Version',
        'version' => SEMVER_1_0,
        'type' => 'entity',
        'core_version' => SEMVER_1_0,
    ];
    $root = createPluginFixture($manifest);

    try {
        $loader = new PluginLoader($root, $pdo);
        $threw = false;
        try {
            $loader->update($slug);
        } catch (\DomainException) {
            $threw = true;
        }

        assertTrue($threw, 'Update should fail when disk version is not greater');
    } finally {
        cleanupPlugin($pdo, $slug);
        removeFixture($root);
    }
});

TestSuite::run('update() fails on non-additive schema change', function () use ($pdo): void {
    $slug = 'test_update_break_' . bin2hex(random_bytes(3));
    $pdo->prepare(
        "INSERT INTO plugins (slug, name, plugin_type, version, status, schema_version, schema_json)
         VALUES (:slug, 'Breaking Plugin', 'entity', '1.0.0', 'inactive', 1, :schema::jsonb)"
    )->execute([
        ':slug' => $slug,
        ':schema' => json_encode([
            'entity' => $slug,
            'fields' => [
                'name' => ['type' => 'string', 'required' => true, 'label' => 'Name'],
            ],
            'custom_fields' => [],
            'relations' => [],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    ]);

    $manifest = [
        'slug' => $slug,
        'name' => 'Breaking Plugin',
        'version' => SEMVER_1_1,
        'type' => 'entity',
        'core_version' => SEMVER_1_0,
    ];
    $schema = [
        'entity' => $slug,
        'version' => SEMVER_1_1,
        'fields' => [
            'name' => ['type' => 'text', 'required' => true, 'label' => 'Name'],
        ],
        'custom_fields' => [],
        'relations' => [],
    ];
    $root = createPluginFixture($manifest, false, false, $schema);

    try {
        $loader = new PluginLoader($root, $pdo);
        $threw = false;
        try {
            $loader->update($slug);
        } catch (\DomainException) {
            $threw = true;
        }

        assertTrue($threw, 'Update should fail on non-additive schema changes');

        $stmt = $pdo->prepare('SELECT version, schema_version FROM plugins WHERE slug = :slug');
        $stmt->execute([SLUG_BIND_PARAM => $slug]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        assertEquals(SEMVER_1_0, (string) ($row['version'] ?? ''), 'Version must stay unchanged');
        assertEquals('1', (string) ($row['schema_version'] ?? ''), 'schema_version must stay unchanged');
    } finally {
        cleanupPlugin($pdo, $slug);
        removeFixture($root);
    }
});

TestSuite::run('update() rolls back plugin and snapshot when onUpdate fails', function () use ($pdo): void {
    $slug = 'test_update_rollback_' . bin2hex(random_bytes(3));
    $pdo->prepare(
        "INSERT INTO plugins (slug, name, plugin_type, version, status, schema_version, schema_json)
         VALUES (:slug, 'Rollback Plugin', 'entity', '1.0.0', 'active', 1, :schema::jsonb)"
    )->execute([
        ':slug' => $slug,
        ':schema' => json_encode([
            'entity' => $slug,
            'fields' => [
                'name' => ['type' => 'string', 'required' => true, 'label' => 'Name'],
            ],
            'custom_fields' => [],
            'relations' => [],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    ]);

    $manifest = [
        'slug' => $slug,
        'name' => 'Rollback Plugin',
        'version' => SEMVER_2_0,
        'type' => 'entity',
        'core_version' => SEMVER_1_0,
    ];
    $schema = [
        'entity' => $slug,
        'version' => SEMVER_2_0,
        'fields' => [
            'name' => ['type' => 'string', 'required' => true, 'label' => 'Name'],
            'email' => ['type' => 'email', 'required' => false, 'label' => 'Email'],
        ],
        'custom_fields' => [],
        'relations' => [],
    ];
    $lifecycle = <<<PHP
<?php
declare(strict_types=1);
namespace Xestify\plugins\\{$slug};
use PDO;
use RuntimeException;
use Xestify\plugins\PluginLifecycleInterface;
final class Lifecycle implements PluginLifecycleInterface {
    public function __construct(private PDO \$pdo) {}
    public function onInstall(): void {}
    public function onActivate(): void {}
    public function onDeactivate(): void {}
    public function onUpdate(array \$context): void {
        throw new RuntimeException('update failed on purpose');
    }
}
PHP;
    $root = createPluginFixture($manifest, false, false, $schema, $lifecycle);

    try {
        $loader = new PluginLoader($root, $pdo);
        $threw = false;
        try {
            $loader->update($slug);
        } catch (\RuntimeException $e) {
            $threw = str_contains($e->getMessage(), 'on purpose');
        }

        assertTrue($threw, 'Update should bubble lifecycle failure');

        $stmt = $pdo->prepare('SELECT version, status, schema_version, schema_json FROM plugins WHERE slug = :slug');
        $stmt->execute([SLUG_BIND_PARAM => $slug]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $decoded = json_decode((string) ($row['schema_json'] ?? '{}'), true);

        assertEquals(SEMVER_1_0, (string) ($row['version'] ?? ''), 'Version must roll back');
        assertEquals('active', (string) ($row['status'] ?? ''), 'Status must roll back');
        assertEquals('1', (string) ($row['schema_version'] ?? ''), 'schema_version must roll back');
        assertTrue(!isset($decoded['fields']['email']), 'Merged schema must not persist after rollback');

        $history = $pdo->prepare('SELECT COUNT(*) AS cnt FROM plugin_update_history WHERE slug = :slug');
        $history->execute([SLUG_BIND_PARAM => $slug]);
        $historyRow = $history->fetch(PDO::FETCH_ASSOC);
        assertEquals('0', (string) ($historyRow['cnt'] ?? '0'), 'Snapshot insert must roll back with transaction');
    } finally {
        cleanupPlugin($pdo, $slug);
        removeFixture($root);
    }
});

TestSuite::run('getOutdated() ignores plugin when disk version is equal', function () use ($pdo): void {
    $slug = 'test_equal_' . bin2hex(random_bytes(3));
    $stmt = $pdo->prepare(
        "INSERT INTO plugins (slug, plugin_type, version, status)
         VALUES (:slug, 'entity', :version, 'inactive')"
    );
    $stmt->execute([SLUG_BIND_PARAM => $slug, ':version' => SEMVER_1_0]);

    $manifest = [
        'slug' => $slug,
        'name' => 'Test Equal',
        'version' => SEMVER_1_0,
        'type' => 'entity',
        'core_version' => SEMVER_1_0,
    ];
    $root = createPluginFixture($manifest);

    try {
        $loader = new PluginLoader($root, $pdo);
        $outdated = $loader->getOutdated();

        foreach ($outdated as $item) {
            assertTrue($item['slug'] !== $slug, 'Should not report plugin when disk version is equal');
        }
    } finally {
        cleanupPlugin($pdo, $slug);
        removeFixture($root);
    }
});

TestSuite::run('getOutdated() ignores plugin when disk version is lower', function () use ($pdo): void {
    $slug = 'test_lower_' . bin2hex(random_bytes(3));
    $stmt = $pdo->prepare(
        "INSERT INTO plugins (slug, plugin_type, version, status) VALUES (:slug, 'entity', :version, 'inactive')"
    );
    $stmt->execute([SLUG_BIND_PARAM => $slug, ':version' => SEMVER_2_0]);

    $manifest = [
        'slug' => $slug,
        'name' => 'Test Lower',
        'version' => SEMVER_1_0,
        'type' => 'entity',
        'core_version' => SEMVER_1_0,
    ];
    $root = createPluginFixture($manifest);

    try {
        $loader = new PluginLoader($root, $pdo);
        $outdated = $loader->getOutdated();

        foreach ($outdated as $item) {
            assertTrue($item['slug'] !== $slug, 'Should not report plugin when disk version is lower');
        }
    } finally {
        cleanupPlugin($pdo, $slug);
        removeFixture($root);
    }
});

// ---------------------------------------------------------------------------
// Summary
// ---------------------------------------------------------------------------

TestSuite::summary();
exit(TestSuite::exitCode());

