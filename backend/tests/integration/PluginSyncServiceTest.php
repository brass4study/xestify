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
    echo "[SKIP] PostgreSQL not reachable — all PluginSyncServiceTest cases skipped.\n";
    echo "       Configure backend/.env with valid DB_* vars and run migrations.\n";
    echo str_repeat('-', 40) . "\n";
    echo "Resultado: 0 passed, 0 failed (skipped)\n";
    exit(0);
}

const SYNC_SLUG_PARAM = ':slug';
const SYNC_SEMVER_1_0 = '1.0.0';
const SYNC_SEMVER_2_0 = '2.0.0';

echo str_repeat('-', 40) . "\n";

TestSuite::run('syncAll() registers new plugin and returns summary', function () use ($pdo): void {
    $slug = 'test_sync_new_' . bin2hex(random_bytes(3));
    $manifest = [
        'slug' => $slug,
        'name' => 'Sync New',
        'version' => SYNC_SEMVER_1_0,
        'type' => 'entity',
        'core_version' => SYNC_SEMVER_1_0,
    ];
    $root = createPluginFixture($manifest);

    try {
        $service = buildPluginSyncService($root, $pdo);
        $result = $service->syncAll();

        assertEquals(1, $result['summary']['discovered'], 'Should discover one plugin');
        assertEquals(1, $result['summary']['registered'], 'Should register one plugin');
        assertEquals('registered', $result['plugins'][$slug]['result'], 'Plugin should be registered');

        $stmt = $pdo->prepare('SELECT version, schema_json FROM plugins WHERE slug = :slug');
        $stmt->execute([SYNC_SLUG_PARAM => $slug]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        assertTrue($row !== false, 'Plugin must exist after sync');
        assertEquals(SYNC_SEMVER_1_0, (string) $row['version'], 'Installed version should match manifest');
        assertTrue($row['schema_json'] !== null, 'Entity schema should be persisted on first sync');
    } finally {
        cleanupPluginRecord($pdo, $slug);
        removePluginFixture($root);
    }
});

TestSuite::run('syncAll() preserves installed runtime for existing outdated plugin', function () use ($pdo): void {
    $slug = 'test_sync_old_' . bin2hex(random_bytes(3));
    $pdo->prepare(
        "INSERT INTO plugins (slug, name, plugin_type, version, status, schema_version, schema_json)
         VALUES (:slug, :name, 'entity', '1.0.0', 'inactive', 4, CAST(:schema AS jsonb))"
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
        'version' => SYNC_SEMVER_2_0,
        'type' => 'entity',
        'core_version' => SYNC_SEMVER_1_0,
    ];
    $schema = [
        'entity' => $slug,
        'version' => SYNC_SEMVER_2_0,
        'fields' => [
            'name' => ['type' => 'string', 'required' => true, 'label' => 'New Label'],
            'email' => ['type' => 'email', 'required' => false, 'label' => 'Email'],
        ],
        'custom_fields' => [],
        'relations' => [],
    ];
    $root = createPluginFixture($manifest, false, false, $schema);

    try {
        $service = buildPluginSyncService($root, $pdo);
        $result = $service->syncAll();

        assertEquals('outdated', $result['plugins'][$slug]['result'], 'Plugin should be reported as outdated');

        $stmt = $pdo->prepare('SELECT name, version, schema_version, schema_json FROM plugins WHERE slug = :slug');
        $stmt->execute([SYNC_SLUG_PARAM => $slug]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        assertTrue($row !== false, 'Plugin must still exist after sync');
        assertEquals('New Name', (string) $row['name'], 'Safe metadata refresh should update name');
        assertEquals(SYNC_SEMVER_1_0, (string) $row['version'], 'Installed version must be preserved');
        assertEquals('4', (string) $row['schema_version'], 'schema_version must be preserved');

        $decoded = json_decode((string) $row['schema_json'], true);
        assertEquals('Old Label', (string) ($decoded['fields']['name']['label'] ?? ''), 'Schema must not be consumed');
        assertTrue(!isset($decoded['fields']['email']), 'New disk fields must not be applied during sync');
    } finally {
        cleanupPluginRecord($pdo, $slug);
        removePluginFixture($root);
    }
});

TestSuite::run('syncAll() reports corrupt installed schema for unchanged entity plugin', function () use ($pdo): void {
    $slug = 'test_sync_corrupt_' . bin2hex(random_bytes(3));
    $corruptSchema = [
        'fields' => [
            'name' => ['type' => 'string', 'required' => true],
        ],
    ];
    $pdo->prepare(
        "INSERT INTO plugins (slug, name, plugin_type, version, status, schema_version, schema_json)
         VALUES (:slug, 'Corrupt Plugin', 'entity', '1.0.0', 'inactive', 26, CAST(:schema AS jsonb))"
    )->execute([
        ':slug' => $slug,
        ':schema' => json_encode($corruptSchema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    ]);

    $manifest = [
        'slug' => $slug,
        'name' => 'Corrupt Plugin',
        'version' => SYNC_SEMVER_1_0,
        'type' => 'entity',
        'core_version' => SYNC_SEMVER_1_0,
    ];
    $schema = [
        'entity' => $slug,
        'version' => SYNC_SEMVER_1_0,
        'identities' => [
            'id' => ['type' => 'uuid', 'auto_generated' => true, 'editable' => false],
        ],
        'fields' => [
            'name' => ['type' => 'string', 'required' => true, 'label' => 'Name'],
            'email' => ['type' => 'email', 'required' => true, 'label' => 'Email'],
        ],
        'custom_fields' => [
            ['key' => 'phone', 'type' => 'string', 'required' => false, 'label' => 'Phone'],
        ],
        'relations' => [],
    ];
    $root = createPluginFixture($manifest, false, false, $schema);

    try {
        $service = buildPluginSyncService($root, $pdo);
        $result = $service->syncAll();

        assertEquals(1, $result['summary']['errors'], 'Corrupt installed schema should be reported as error');
        assertEquals('error', $result['plugins'][$slug]['result'], 'Plugin result should be error');

        $stmt = $pdo->prepare('SELECT schema_version, schema_json FROM plugins WHERE slug = :slug');
        $stmt->execute([SYNC_SLUG_PARAM => $slug]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $decoded = json_decode((string) ($row['schema_json'] ?? '{}'), true);

        assertEquals('26', (string) ($row['schema_version'] ?? ''), 'sync must not repair schema_version implicitly');
        assertTrue(!isset($decoded['fields']['email']), 'sync must not repair schema_json implicitly');
    } finally {
        cleanupPluginRecord($pdo, $slug);
        removePluginFixture($root);
    }
});

TestSuite::run('syncAll() reports invalid manifest without aborting batch', function () use ($pdo): void {
    $goodSlug = 'test_sync_good_' . bin2hex(random_bytes(3));
    $badSlug = 'test_sync_bad_' . bin2hex(random_bytes(3));
    $root = sys_get_temp_dir() . '/xestify_sync_batch_' . bin2hex(random_bytes(4));
    mkdir($root, 0777, true);

    $goodDir = $root . '/' . $goodSlug;
    mkdir($goodDir, 0777, true);
    file_put_contents($goodDir . '/manifest.json', (string) json_encode([
        'slug' => $goodSlug,
        'name' => 'Good',
        'version' => SYNC_SEMVER_1_0,
        'type' => 'extension',
        'core_version' => SYNC_SEMVER_1_0,
    ]));

    $badDir = $root . '/' . $badSlug;
    mkdir($badDir, 0777, true);
    file_put_contents($badDir . '/manifest.json', '{invalid');

    try {
        $service = buildPluginSyncService($root, $pdo);
        $result = $service->syncAll();

        assertEquals(2, $result['summary']['discovered'], 'Should discover both plugin directories');
        assertEquals(1, $result['summary']['registered'], 'Should register the valid plugin');
        assertEquals(1, $result['summary']['errors'], 'Should report one sync error');
        assertEquals('registered', $result['plugins'][$goodSlug]['result'], 'Valid plugin should be registered');
        assertEquals('error', $result['plugins'][$badSlug]['result'], 'Invalid plugin should be reported as error');
    } finally {
        cleanupPluginRecord($pdo, $goodSlug);
        cleanupPluginRecord($pdo, $badSlug);
        removePluginFixture($root);
    }
});

echo str_repeat('-', 40) . "\n";
TestSuite::summary();
exit(TestSuite::exitCode());
