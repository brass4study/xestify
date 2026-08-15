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
const MSG_PLUGIN_MUST_EXIST_AFTER_SYNC = 'Plugin must exist after sync';
const SCHEMA_JSON_SUFFIX = '/schema.json';
const SELECT_SCHEMA_JSON_BY_SLUG_SQL = 'SELECT schema_json FROM plugins WHERE slug = :slug';

/**
 * Seeds a plugins row directly with the new manifest_json shape (STORY 10.3
 * §2bis) — used by tests that need to seed a pre-existing installed row
 * before syncAll() runs against it.
 *
 * @param array<string, mixed> $manifestOverrides merged over a minimal default manifest
 * @param array<string, mixed>|null $schema
 */
function insertSyncTestPlugin(PDO $pdo, string $slug, array $manifestOverrides, string $status, ?array $schema): void
{
    $manifest = array_merge([
        'name' => $slug,
        'label' => $slug,
        'version' => SYNC_SEMVER_1_0,
        'type' => 'entity',
        'core_version' => SYNC_SEMVER_1_0,
        'description' => '',
    ], $manifestOverrides);

    $stmt = $pdo->prepare(
        'INSERT INTO plugins (slug, status, manifest_json, schema_json)
         VALUES (:slug, :status, CAST(:manifest AS jsonb), CAST(:schema AS jsonb))'
    );
    $stmt->execute([
        ':slug' => $slug,
        ':status' => $status,
        ':manifest' => json_encode($manifest, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ':schema' => $schema === null ? null : json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    ]);
}

echo str_repeat('-', 40) . "\n";

TestSuite::run('syncAll() registers new plugin and returns summary', function () use ($pdo): void {
    $slug = 'test_sync_new_' . bin2hex(random_bytes(3));
    $manifest = [
        'name' => $slug,
        'label' => 'Sync New',
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
        assertEquals('registered', $result['plugins'][$slug][0]['result'], 'Plugin should be registered');

        $stmt = $pdo->prepare("SELECT manifest_json->>'version' AS version, schema_json FROM plugins WHERE slug = :slug");
        $stmt->execute([SYNC_SLUG_PARAM => $slug]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        assertTrue($row !== false, MSG_PLUGIN_MUST_EXIST_AFTER_SYNC);
        assertEquals(SYNC_SEMVER_1_0, (string) $row['version'], 'Installed version should match manifest');
        assertTrue($row['schema_json'] !== null, 'Entity schema should be persisted on first sync');
    } finally {
        cleanupPluginRecord($pdo, $slug);
        removePluginFixture($root);
    }
});

TestSuite::run('syncAll() rolls back plugin registration when onInstall fails', function () use ($pdo): void {
    $slug = 'test_sync_install_fail_' . bin2hex(random_bytes(3));
    $manifest = [
        'name' => $slug,
        'label' => 'Install Fails',
        'version' => SYNC_SEMVER_1_0,
        'type' => 'entity',
        'core_version' => SYNC_SEMVER_1_0,
    ];
    $lifecycle = <<<PHP
<?php
declare(strict_types=1);
namespace Xestify\plugins\\{$slug};
use PDO;
use RuntimeException;
use Xestify\plugins\contracts\PluginLifecycleInterface;
final class Lifecycle implements PluginLifecycleInterface {
    public function __construct(private PDO \$pdo) {}
    public function onInstall(): void { throw new RuntimeException('install failed on purpose'); }
    public function onActivate(): void {}
    public function onDeactivate(): void {}
}
PHP;
    $root = createPluginFixture($manifest, false, false, null, $lifecycle);

    try {
        $service = buildPluginSyncService($root, $pdo);
        $result = $service->syncAll();

        assertEquals(1, $result['summary']['errors'], 'onInstall failure should be reported as sync error');
        assertEquals('error', $result['plugins'][$slug][0]['result'], 'Plugin result should be error');

        $stmt = $pdo->prepare('SELECT COUNT(*) AS cnt FROM plugins WHERE slug = :slug');
        $stmt->execute([SYNC_SLUG_PARAM => $slug]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        assertEquals(
            '0',
            (string) ($row['cnt'] ?? '1'),
            'Plugin row must not persist when onInstall fails, or it is stuck uninitialized forever (04.04)'
        );
    } finally {
        cleanupPluginRecord($pdo, $slug);
        removePluginFixture($root);
    }
});

TestSuite::run('syncAll() never overwrites admin-owned name once installed (STORY 10.3)', function () use ($pdo): void {
    $slug = 'test_sync_old_' . bin2hex(random_bytes(3));
    insertSyncTestPlugin($pdo, $slug, ['label' => 'Old Name'], 'inactive', [
        'fields' => [
            'name' => ['type' => 'string', 'required' => true, 'label' => 'Old Label'],
        ],
        'custom_fields' => [],
        'relations' => [],
    ]);

    $manifest = [
        'name' => $slug,
        'label' => 'New Name',
        'version' => SYNC_SEMVER_2_0,
        'type' => 'entity',
        'core_version' => SYNC_SEMVER_1_0,
    ];
    $schema = [
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

        assertEquals('outdated', $result['plugins'][$slug][0]['result'], 'Plugin should be reported as outdated');

        $stmt = $pdo->prepare(
            "SELECT manifest_json->>'label' AS name, manifest_json->>'version' AS version, schema_json
             FROM plugins WHERE slug = :slug"
        );
        $stmt->execute([SYNC_SLUG_PARAM => $slug]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        assertTrue($row !== false, 'Plugin must still exist after sync');
        assertEquals(
            'Old Name',
            (string) $row['name'],
            'name is admin-owned after install; sync must never overwrite it from the manifest (STORY 10.3)'
        );
        assertEquals(SYNC_SEMVER_1_0, (string) $row['version'], 'Installed version must be preserved');

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
    insertSyncTestPlugin($pdo, $slug, ['label' => 'Corrupt Plugin'], 'inactive', $corruptSchema);

    $manifest = [
        'name' => $slug,
        'label' => 'Corrupt Plugin',
        'version' => SYNC_SEMVER_1_0,
        'type' => 'entity',
        'core_version' => SYNC_SEMVER_1_0,
    ];
    $schema = [
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
        assertEquals('error', $result['plugins'][$slug][0]['result'], 'Plugin result should be error');

        $stmt = $pdo->prepare(SELECT_SCHEMA_JSON_BY_SLUG_SQL);
        $stmt->execute([SYNC_SLUG_PARAM => $slug]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $decoded = json_decode((string) ($row['schema_json'] ?? '{}'), true);

        assertTrue(!isset($decoded['fields']['email']), 'sync must not repair schema_json implicitly');
    } finally {
        cleanupPluginRecord($pdo, $slug);
        removePluginFixture($root);
    }
});

TestSuite::run('syncAll() reports corrupt installed schema for unchanged extension plugin', function () use ($pdo): void {
    $slug = 'test_sync_ext_corrupt_' . bin2hex(random_bytes(3));
    insertSyncTestPlugin($pdo, $slug, [
        'label' => 'Corrupt Extension',
        'type' => 'extension',
        'target_entity' => '*',
    ], 'inactive', [
        'fields' => [
            'body' => ['type' => 'text', 'required' => true, 'label' => 'Body'],
        ],
    ]);

    $manifest = [
        'name' => $slug,
        'label' => 'Corrupt Extension',
        'version' => SYNC_SEMVER_1_0,
        'type' => 'extension',
        'core_version' => SYNC_SEMVER_1_0,
    ];
    $root = createPluginFixture($manifest);
    file_put_contents($root . '/' . $slug . SCHEMA_JSON_SUFFIX, (string) json_encode([
        'fields' => [
            'body' => ['type' => 'text', 'required' => true, 'label' => 'Body'],
            'stamp' => ['type' => 'timestamp', 'required' => false, 'label' => 'Stamp', 'auto_generated' => true],
        ],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

    try {
        $service = buildPluginSyncService($root, $pdo);
        $result = $service->syncAll();

        assertEquals(1, $result['summary']['errors'], 'Corrupt installed extension schema should be reported as error (04.02)');
        assertEquals('error', $result['plugins'][$slug][0]['result'], 'Extension result should be error');
    } finally {
        cleanupPluginRecord($pdo, $slug);
        removePluginFixture($root);
    }
});

TestSuite::run('syncAll() does not report corruption for a configured plugin with an inactive suggested field', function () use ($pdo): void {
    // Once PluginAdministrationService::saveConfig() runs, custom_fields means
    // "active fields" (the admin left "phone" inactive here) and the real
    // catalog moves to plugin_suggested_custom_fields. A routine unchanged-
    // version sync must compare canonical custom_fields against that catalog,
    // not against the active list, or a perfectly valid inactive suggestion
    // gets reported as schema corruption.
    $slug = 'test_sync_configured_' . bin2hex(random_bytes(3));
    $installedSchema = [
        'identities' => [
            'id' => ['type' => 'uuid', 'auto_generated' => true, 'editable' => false],
        ],
        'fields' => [
            'name' => ['type' => 'string', 'required' => true, 'label' => 'Name'],
        ],
        'custom_fields' => [],
        'plugin_suggested_custom_fields' => [
            ['key' => 'phone', 'type' => 'string', 'required' => false, 'label' => 'Phone'],
        ],
        'relations' => [],
    ];
    insertSyncTestPlugin($pdo, $slug, ['label' => 'Configured Plugin'], 'active', $installedSchema);

    $manifest = [
        'name' => $slug,
        'label' => 'Configured Plugin',
        'version' => SYNC_SEMVER_1_0,
        'type' => 'entity',
        'core_version' => SYNC_SEMVER_1_0,
    ];
    $schema = [
        'identities' => [
            'id' => ['type' => 'uuid', 'auto_generated' => true, 'editable' => false],
        ],
        'fields' => [
            'name' => ['type' => 'string', 'required' => true, 'label' => 'Name'],
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

        assertEquals(0, $result['summary']['errors'], 'A configured plugin with an inactive suggested field must not be reported as corrupt');
        assertEquals('unchanged', $result['plugins'][$slug][0]['result'], 'Plugin should sync as unchanged, not error');
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
        'name' => $goodSlug,
        'label' => 'Good',
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
        assertEquals('registered', $result['plugins'][$goodSlug][0]['result'], 'Valid plugin should be registered');
        assertEquals('error', $result['plugins'][$badSlug][0]['result'], 'Invalid plugin should be reported as error');
    } finally {
        cleanupPluginRecord($pdo, $goodSlug);
        cleanupPluginRecord($pdo, $badSlug);
        removePluginFixture($root);
    }
});

TestSuite::run('syncAll() persists extension schema on first registration when schema.json exists', function () use ($pdo): void {
    $slug = 'test_sync_ext_schema_' . bin2hex(random_bytes(3));
    $manifest = [
        'name' => $slug,
        'label' => 'Extension With Schema',
        'version' => SYNC_SEMVER_1_0,
        'type' => 'extension',
        'core_version' => SYNC_SEMVER_1_0,
        'target_entity' => '*',
    ];
    $root = createPluginFixture($manifest);
    $schemaPath = $root . '/' . $slug . SCHEMA_JSON_SUFFIX;
    file_put_contents(
        $schemaPath,
        (string) json_encode([
            'fields' => [
                'body' => ['type' => 'text', 'required' => true, 'label' => 'Body'],
                'stamp' => ['type' => 'timestamp', 'required' => false, 'label' => 'Stamp', 'auto_generated' => true],
            ],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
    );

    try {
        $service = buildPluginSyncService($root, $pdo);
        $result = $service->syncAll();

        assertEquals('registered', $result['plugins'][$slug][0]['result'], 'Extension should be registered');

        $stmt = $pdo->prepare(SELECT_SCHEMA_JSON_BY_SLUG_SQL);
        $stmt->execute([SYNC_SLUG_PARAM => $slug]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        assertTrue($row !== false, MSG_PLUGIN_MUST_EXIST_AFTER_SYNC);
        $decoded = json_decode((string) ($row['schema_json'] ?? ''), true);
        assertTrue(is_array($decoded), 'Extension schema_json should be persisted');
        assertTrue(isset($decoded['fields']['body']), 'Persisted schema must contain body field');
    } finally {
        cleanupPluginRecord($pdo, $slug);
        removePluginFixture($root);
    }
});

TestSuite::run('syncAll() backfills missing extension schema_json from disk schema', function () use ($pdo): void {
    $slug = 'test_sync_ext_backfill_' . bin2hex(random_bytes(3));
    insertSyncTestPlugin($pdo, $slug, [
        'label' => 'Backfill Extension',
        'type' => 'extension',
        'target_entity' => '*',
    ], 'inactive', null);

    $manifest = [
        'name' => $slug,
        'label' => 'Backfill Extension',
        'version' => SYNC_SEMVER_1_0,
        'type' => 'extension',
        'core_version' => SYNC_SEMVER_1_0,
        'target_entity' => '*',
    ];
    $root = createPluginFixture($manifest);
    $schemaPath = $root . '/' . $slug . SCHEMA_JSON_SUFFIX;
    file_put_contents(
        $schemaPath,
        (string) json_encode([
            'fields' => [
                'body' => ['type' => 'text', 'required' => true, 'label' => 'Body'],
            ],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
    );

    try {
        $service = buildPluginSyncService($root, $pdo);
        $result = $service->syncAll();

        assertEquals('unchanged', $result['plugins'][$slug][0]['result'], 'Extension should remain unchanged');

        $stmt = $pdo->prepare(SELECT_SCHEMA_JSON_BY_SLUG_SQL);
        $stmt->execute([SYNC_SLUG_PARAM => $slug]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        assertTrue($row !== false, MSG_PLUGIN_MUST_EXIST_AFTER_SYNC);
        $decoded = json_decode((string) ($row['schema_json'] ?? ''), true);
        assertTrue(is_array($decoded), 'Missing schema_json should be backfilled');
    } finally {
        cleanupPluginRecord($pdo, $slug);
        removePluginFixture($root);
    }
});

TestSuite::run('syncAll() processes every instance independently when a plugin_name has multiple rows', function () use ($pdo): void {
    $pluginName = 'test_sync_multi_' . bin2hex(random_bytes(3));
    $slugA = $pluginName . '_a';
    $slugB = $pluginName . '_b';

    foreach ([$slugA, $slugB] as $slug) {
        insertSyncTestPlugin($pdo, $slug, ['name' => $pluginName, 'label' => 'Multi Instance ' . $slug], 'inactive', [
            'fields' => [
                'name' => ['type' => 'string', 'required' => true, 'label' => 'Name'],
            ],
            'custom_fields' => [],
            'relations' => [],
        ]);
    }

    $manifest = [
        'name' => $pluginName,
        'label' => 'Multi Instance Plugin',
        'version' => SYNC_SEMVER_2_0,
        'type' => 'entity',
        'core_version' => SYNC_SEMVER_1_0,
    ];
    $schema = [
        'fields' => [
            'name' => ['type' => 'string', 'required' => true, 'label' => 'Name'],
        ],
        'custom_fields' => [],
        'relations' => [],
    ];
    $root = createPluginFixture($manifest, false, false, $schema);

    try {
        $service = buildPluginSyncService($root, $pdo);
        $result = $service->syncAll();

        $instanceResults = $result['plugins'][$pluginName] ?? [];
        assertEquals(2, count($instanceResults), 'Both instances of the same plugin_name must be processed');

        $bySlug = [];
        foreach ($instanceResults as $instanceResult) {
            $bySlug[$instanceResult['slug']] = $instanceResult;
        }

        assertEquals('outdated', $bySlug[$slugA]['result'] ?? null, 'Instance A should be reported as outdated');
        assertEquals('outdated', $bySlug[$slugB]['result'] ?? null, 'Instance B should be reported as outdated');
        assertEquals(2, $result['summary']['outdated'], 'Summary must count both outdated instances');

        $countStmt = $pdo->prepare("SELECT COUNT(*) AS cnt FROM plugins WHERE manifest_json->>'name' = :plugin_name");
        $countStmt->execute([':plugin_name' => $pluginName]);
        $countRow = $countStmt->fetch(PDO::FETCH_ASSOC);
        assertEquals(
            '2',
            (string) ($countRow['cnt'] ?? '0'),
            'Sync must not create a third instance nor delete existing ones'
        );
    } finally {
        cleanupPluginRecord($pdo, $pluginName);
        removePluginFixture($root);
    }
});

TestSuite::run('syncAll() recognizes a renamed instance by plugin_name and does not create a duplicate row (STORY 10.3)', function () use ($pdo): void {
    $pluginName = 'test_sync_rename_' . bin2hex(random_bytes(3));
    $renamedSlug = $pluginName . '_renamed';

    insertSyncTestPlugin($pdo, $renamedSlug, ['name' => $pluginName, 'label' => 'Renamed Sync Plugin'], 'inactive', [
        'identities' => [
            'id' => ['type' => 'uuid', 'auto_generated' => true, 'editable' => false],
        ],
        'fields' => [
            'name' => ['type' => 'string', 'required' => true, 'label' => 'Name'],
        ],
        'custom_fields' => [],
        'relations' => [],
    ]);

    $manifest = [
        'name' => $pluginName,
        'label' => 'Renamed Sync Plugin',
        'version' => SYNC_SEMVER_1_0,
        'type' => 'entity',
        'core_version' => SYNC_SEMVER_1_0,
    ];
    // No explicit schema: createPluginFixture()'s default entity schema
    // matches the shape inserted above exactly (same identities/fields).
    $root = createPluginFixture($manifest);

    try {
        $service = buildPluginSyncService($root, $pdo);
        $result = $service->syncAll();

        $instanceResults = $result['plugins'][$pluginName] ?? [];
        assertEquals(1, count($instanceResults), 'Only the renamed instance should be reported, not a new one');
        assertEquals($renamedSlug, $instanceResults[0]['slug'] ?? null, 'Sync must report the current (renamed) slug');
        assertEquals('unchanged', $instanceResults[0]['result'] ?? null, 'A matching version must resolve to unchanged, not registered');

        $countStmt = $pdo->prepare("SELECT COUNT(*) AS cnt FROM plugins WHERE manifest_json->>'name' = :plugin_name");
        $countStmt->execute([':plugin_name' => $pluginName]);
        $countRow = $countStmt->fetch(PDO::FETCH_ASSOC);
        assertEquals('1', (string) ($countRow['cnt'] ?? '0'), 'Sync after a rename must not create a duplicate row for the same plugin_name');
    } finally {
        cleanupPluginRecord($pdo, $pluginName);
        removePluginFixture($root);
    }
});

echo str_repeat('-', 40) . "\n";
TestSuite::summary();
exit(TestSuite::exitCode());
