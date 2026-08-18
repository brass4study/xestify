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
    echo "[SKIP] PostgreSQL not reachable — all PluginUpdateServiceTest cases skipped.\n";
    echo "       Configure backend/.env with valid DB_* vars and run php tools/setup/install.php.\n";
    echo str_repeat('-', 40) . "\n";
    echo "Resultado: 0 passed, 0 failed (skipped)\n";
    exit(0);
}

const UPDATE_SLUG_PARAM = ':slug';
const UPDATE_VERSION_1_0 = '1.0.0';
const UPDATE_VERSION_1_1 = '1.1.0';
const UPDATE_VERSION_2_0 = '2.0.0';
const MSG_VERSION_SHOULD_BE_UPDATED = 'Version should be updated';
const SELECT_VERSION_SCHEMA_BY_SLUG_SQL = "SELECT manifest_json->>'version' AS version, schema_json FROM plugins WHERE slug = :slug";

/**
 * Seeds a plugins row with the new manifest_json shape (STORY 10.3 §2bis).
 *
 * @param array<string, mixed> $manifestOverrides merged over a minimal default manifest
 * @param array<string, mixed>|null $schema
 */
function insertUpdateTestPlugin(PDO $pdo, string $slug, array $manifestOverrides, string $status, ?array $schema): void
{
    $manifest = array_merge([
        'name' => $slug,
        'label' => $slug,
        'version' => UPDATE_VERSION_1_0,
        'type' => 'entity',
        'core_version' => UPDATE_VERSION_1_0,
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

TestSuite::run('update() upgrades plugin version without schema changes and stores snapshot', function () use ($pdo): void {
    $slug = 'test_update_ver_' . bin2hex(random_bytes(3));
    insertUpdateTestPlugin($pdo, $slug, ['label' => 'Versioned Plugin'], 'inactive', [
        'fields' => [
            'name' => ['type' => 'string', 'required' => true, 'label' => 'Name'],
        ],
        'custom_fields' => [],
        'relations' => [],
    ]);

    $manifest = [
        'name' => $slug,
        'label' => 'Versioned Plugin',
        'version' => UPDATE_VERSION_2_0,
        'type' => 'entity',
        'core_version' => UPDATE_VERSION_1_0,
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
        $service = buildPluginUpdateService($root, $pdo);
        $result = $service->update($slug);

        assertEquals(UPDATE_VERSION_1_0, $result['update']['from_version'], 'Should report old version');
        assertEquals(UPDATE_VERSION_2_0, $result['update']['to_version'], 'Should report new version');
        assertTrue($result['update']['schema_changed'] === false, 'Schema should remain unchanged');

        $stmt = $pdo->prepare("SELECT manifest_json->>'version' AS version FROM plugins WHERE slug = :slug");
        $stmt->execute([UPDATE_SLUG_PARAM => $slug]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        assertEquals(UPDATE_VERSION_2_0, (string) ($row['version'] ?? ''), 'Installed version should be upgraded');

        $history = $pdo->prepare(
            'SELECT COUNT(*) AS cnt FROM plugin_update_history WHERE slug = :slug AND target_version = :target'
        );
        $history->execute([UPDATE_SLUG_PARAM => $slug, ':target' => UPDATE_VERSION_2_0]);
        $historyRow = $history->fetch(PDO::FETCH_ASSOC);
        assertEquals('1', (string) ($historyRow['cnt'] ?? '0'), 'Successful update should create one snapshot');
    } finally {
        cleanupPluginRecord($pdo, $slug);
        removePluginFixture($root);
    }
});

TestSuite::run('update() merges additive schema changes', function () use ($pdo): void {
    $slug = 'test_update_schema_' . bin2hex(random_bytes(3));
    insertUpdateTestPlugin($pdo, $slug, ['label' => 'Schema Plugin'], 'inactive', [
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
        'name' => $slug,
        'label' => 'Schema Plugin',
        'version' => UPDATE_VERSION_1_1,
        'type' => 'entity',
        'core_version' => UPDATE_VERSION_1_0,
    ];
    $schema = [
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
        $service = buildPluginUpdateService($root, $pdo);
        $result = $service->update($slug);

        assertTrue($result['update']['schema_changed'] === true, 'Schema should change on additive update');
        assertTrue(in_array('email', $result['update']['diff']['fields']['added'], true), 'email field added');
        assertTrue(
            in_array('phone', $result['update']['diff']['custom_fields']['added'], true),
            'phone custom field added'
        );

        $stmt = $pdo->prepare(SELECT_VERSION_SCHEMA_BY_SLUG_SQL);
        $stmt->execute([UPDATE_SLUG_PARAM => $slug]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $decoded = json_decode((string) ($row['schema_json'] ?? '{}'), true);

        assertEquals(UPDATE_VERSION_1_1, (string) ($row['version'] ?? ''), MSG_VERSION_SHOULD_BE_UPDATED);
        assertTrue(isset($decoded['fields']['email']), 'New field should be merged into live schema');
    } finally {
        cleanupPluginRecord($pdo, $slug);
        removePluginFixture($root);
    }
});

TestSuite::run('update() merges additive schema changes for extension plugins', function () use ($pdo): void {
    $slug = 'test_update_ext_' . bin2hex(random_bytes(3));
    insertUpdateTestPlugin($pdo, $slug, [
        'label' => 'Extension Plugin',
        'type' => 'extension',
        'target_entity' => '*',
    ], 'inactive', [
        'fields' => [
            'body' => ['type' => 'text', 'required' => true, 'label' => 'Body'],
        ],
    ]);

    $manifest = [
        'name' => $slug,
        'label' => 'Extension Plugin',
        'version' => UPDATE_VERSION_1_1,
        'type' => 'extension',
        'core_version' => UPDATE_VERSION_1_0,
    ];
    $root = createPluginFixture($manifest);
    file_put_contents($root . '/' . $slug . '/schema.json', (string) json_encode([
        'fields' => [
            'body' => ['type' => 'text', 'required' => true, 'label' => 'Body'],
            'stamp' => ['type' => 'timestamp', 'required' => false, 'label' => 'Stamp', 'auto_generated' => true],
        ],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

    try {
        $service = buildPluginUpdateService($root, $pdo);
        $result = $service->update($slug);

        assertTrue($result['update']['schema_changed'] === true, 'Extension schema diff must propagate on update (04.02)');
        assertTrue(
            in_array('stamp', $result['update']['diff']['fields']['added'], true),
            'stamp field should be reported as added'
        );

        $stmt = $pdo->prepare(SELECT_VERSION_SCHEMA_BY_SLUG_SQL);
        $stmt->execute([UPDATE_SLUG_PARAM => $slug]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $decoded = json_decode((string) ($row['schema_json'] ?? '{}'), true);

        assertEquals(UPDATE_VERSION_1_1, (string) ($row['version'] ?? ''), MSG_VERSION_SHOULD_BE_UPDATED);
        assertTrue(
            isset($decoded['fields']['stamp']),
            'New field must be merged into the installed extension schema, not silently dropped'
        );
    } finally {
        cleanupPluginRecord($pdo, $slug);
        removePluginFixture($root);
    }
});

TestSuite::run('update() applies an update after the plugin was configured, without losing edits or auto-activating new fields', function () use ($pdo): void {
    // Simulates the state PluginAdministrationService::saveConfig() leaves
    // behind: custom_fields holds the admin-curated *active* fields (here,
    // "phone" activated with an edited label), and the real design catalog
    // moved to plugin_suggested_custom_fields.
    $slug = 'test_update_configured_' . bin2hex(random_bytes(3));
    insertUpdateTestPlugin($pdo, $slug, ['label' => 'Configured Plugin'], 'inactive', [
        'identities' => [
            'id' => ['type' => 'uuid', 'auto_generated' => true, 'editable' => false],
        ],
        'fields' => [
            'name' => ['type' => 'string', 'required' => true, 'label' => 'Name'],
        ],
        'custom_fields' => [
            ['key' => 'phone', 'type' => 'string', 'required' => false, 'label' => 'Mobile phone'],
        ],
        'plugin_suggested_custom_fields' => [
            ['key' => 'phone', 'type' => 'string', 'required' => false, 'label' => 'Phone'],
        ],
        'relations' => [],
    ]);

    $manifest = [
        'name' => $slug,
        'label' => 'Configured Plugin',
        'version' => UPDATE_VERSION_1_1,
        'type' => 'entity',
        'core_version' => UPDATE_VERSION_1_0,
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
            ['key' => 'stock', 'type' => 'number', 'required' => false, 'label' => 'Stock'],
        ],
        'relations' => [],
    ];
    $root = createPluginFixture($manifest, false, false, $schema);

    try {
        $service = buildPluginUpdateService($root, $pdo);
        $result = $service->update($slug);

        assertTrue($result['update']['schema_changed'] === true, 'A new suggested field should still be reported in the diff');
        assertTrue(
            in_array('stock', $result['update']['diff']['custom_fields']['added'], true),
            'diff should report stock as added to the catalog'
        );

        $stmt = $pdo->prepare(SELECT_VERSION_SCHEMA_BY_SLUG_SQL);
        $stmt->execute([UPDATE_SLUG_PARAM => $slug]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $decoded = json_decode((string) ($row['schema_json'] ?? '{}'), true);

        assertEquals(UPDATE_VERSION_1_1, (string) ($row['version'] ?? ''), MSG_VERSION_SHOULD_BE_UPDATED);
        assertEquals(1, count($decoded['custom_fields'] ?? []), 'Active custom_fields must not gain the new suggestion');
        assertEquals(
            'Mobile phone',
            $decoded['custom_fields'][0]['label'] ?? null,
            'The admin edit to an already active suggested field must survive the update'
        );
        assertTrue(
            in_array(
                'stock',
                array_column($decoded['plugin_suggested_custom_fields'] ?? [], 'key'),
                true
            ),
            'The new suggested field must be added to the catalog'
        );
    } finally {
        cleanupPluginRecord($pdo, $slug);
        removePluginFixture($root);
    }
});

TestSuite::run('update() fails when disk version is not greater', function () use ($pdo): void {
    $slug = 'test_update_same_' . bin2hex(random_bytes(3));
    insertUpdateTestPlugin($pdo, $slug, ['label' => 'Same Version'], 'inactive', [
        'fields' => [
            'name' => ['type' => 'string', 'required' => true, 'label' => 'Name'],
        ],
        'custom_fields' => [],
        'relations' => [],
    ]);

    $root = createPluginFixture([
        'name' => $slug,
        'label' => 'Same Version',
        'version' => UPDATE_VERSION_1_0,
        'type' => 'entity',
        'core_version' => UPDATE_VERSION_1_0,
    ]);

    try {
        $service = buildPluginUpdateService($root, $pdo);
        $threw = false;
        try {
            $service->update($slug);
        } catch (DomainException) {
            $threw = true;
        }

        assertTrue($threw, 'Update should fail when disk version is not greater');
    } finally {
        cleanupPluginRecord($pdo, $slug);
        removePluginFixture($root);
    }
});

TestSuite::run('update() fails on non-additive schema change', function () use ($pdo): void {
    $slug = 'test_update_break_' . bin2hex(random_bytes(3));
    insertUpdateTestPlugin($pdo, $slug, ['label' => 'Breaking Plugin'], 'inactive', [
        'fields' => [
            'name' => ['type' => 'string', 'required' => true, 'label' => 'Name'],
        ],
        'custom_fields' => [],
        'relations' => [],
    ]);

    $schema = [
        'fields' => [
            'name' => ['type' => 'text', 'required' => true, 'label' => 'Name'],
        ],
        'custom_fields' => [],
        'relations' => [],
    ];
    $root = createPluginFixture([
        'name' => $slug,
        'label' => 'Breaking Plugin',
        'version' => UPDATE_VERSION_1_1,
        'type' => 'entity',
        'core_version' => UPDATE_VERSION_1_0,
    ], false, false, $schema);

    try {
        $service = buildPluginUpdateService($root, $pdo);
        $threw = false;
        try {
            $service->update($slug);
        } catch (DomainException) {
            $threw = true;
        }

        assertTrue($threw, 'Update should fail on non-additive schema changes');
    } finally {
        cleanupPluginRecord($pdo, $slug);
        removePluginFixture($root);
    }
});

TestSuite::run('update() fails when installed schema is corrupt', function () use ($pdo): void {
    $slug = 'test_update_corrupt_' . bin2hex(random_bytes(3));
    insertUpdateTestPlugin($pdo, $slug, ['label' => 'Corrupt Installed'], 'inactive', [
        'fields' => [
            'name' => ['type' => 'string', 'required' => true],
        ],
    ]);

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
    $root = createPluginFixture([
        'name' => $slug,
        'label' => 'Corrupt Installed',
        'version' => UPDATE_VERSION_1_1,
        'type' => 'entity',
        'core_version' => UPDATE_VERSION_1_0,
    ], false, false, $schema);

    try {
        $service = buildPluginUpdateService($root, $pdo);
        $threw = false;
        try {
            $service->update($slug);
        } catch (DomainException) {
            $threw = true;
        }

        assertTrue($threw, 'Update should fail when installed schema is corrupt');

        $stmt = $pdo->prepare(SELECT_VERSION_SCHEMA_BY_SLUG_SQL);
        $stmt->execute([UPDATE_SLUG_PARAM => $slug]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $decoded = json_decode((string) ($row['schema_json'] ?? '{}'), true);

        assertEquals(UPDATE_VERSION_1_0, (string) ($row['version'] ?? ''), 'Version must remain unchanged');
        assertTrue(!isset($decoded['fields']['email']), 'Corrupt schema must not be repaired by update');
    } finally {
        cleanupPluginRecord($pdo, $slug);
        removePluginFixture($root);
    }
});

TestSuite::run('update() rolls back plugin and snapshot when onUpdate fails', function () use ($pdo): void {
    $slug = 'test_update_rollback_' . bin2hex(random_bytes(3));
    insertUpdateTestPlugin($pdo, $slug, ['label' => 'Rollback Plugin'], 'active', [
        'fields' => [
            'name' => ['type' => 'string', 'required' => true, 'label' => 'Name'],
        ],
        'custom_fields' => [],
        'relations' => [],
    ]);

    $schema = [
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
use Xestify\plugins\contracts\PluginLifecycleUpdateInterface;
final class Lifecycle implements PluginLifecycleUpdateInterface {
    public function __construct(private PDO \$pdo) {}
    public function onInstall(): void {}
    public function onActivate(): void {}
    public function onDeactivate(): void {}
    public function onUpdate(array \$context): void {
        throw new RuntimeException('update failed on purpose');
    }
    public function onRollback(array \$context): void {}
}
PHP;
    $root = createPluginFixture([
        'name' => $slug,
        'label' => 'Rollback Plugin',
        'version' => UPDATE_VERSION_2_0,
        'type' => 'entity',
        'core_version' => UPDATE_VERSION_1_0,
    ], false, false, $schema, $lifecycle);

    try {
        $service = buildPluginUpdateService($root, $pdo);
        $threw = false;
        try {
            $service->update($slug);
        } catch (RuntimeException $e) {
            $threw = str_contains($e->getMessage(), 'on purpose');
        }

        assertTrue($threw, 'Update should bubble lifecycle failure');

        $stmt = $pdo->prepare("SELECT manifest_json->>'version' AS version, status, schema_json FROM plugins WHERE slug = :slug");
        $stmt->execute([UPDATE_SLUG_PARAM => $slug]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $decoded = json_decode((string) ($row['schema_json'] ?? '{}'), true);

        assertEquals(UPDATE_VERSION_1_0, (string) ($row['version'] ?? ''), 'Version must roll back');
        assertEquals('active', (string) ($row['status'] ?? ''), 'Status must roll back');
        assertTrue(!isset($decoded['fields']['email']), 'Merged schema must not persist after rollback');

        $history = $pdo->prepare('SELECT COUNT(*) AS cnt FROM plugin_update_history WHERE slug = :slug');
        $history->execute([UPDATE_SLUG_PARAM => $slug]);
        $historyRow = $history->fetch(PDO::FETCH_ASSOC);
        assertEquals('0', (string) ($historyRow['cnt'] ?? '0'), 'Snapshot insert must roll back');
    } finally {
        cleanupPluginRecord($pdo, $slug);
        removePluginFixture($root);
    }
});

TestSuite::run('update() reads the disk manifest and invokes lifecycle by plugin_name after a rename (STORY 10.3)', function () use ($pdo): void {
    $pluginName = 'test_update_rename_' . bin2hex(random_bytes(3));
    $renamedSlug = $pluginName . '_renamed';

    insertUpdateTestPlugin($pdo, $renamedSlug, ['name' => $pluginName, 'label' => 'Renamed Update Plugin'], 'active', [
        'fields' => [
            'name' => ['type' => 'string', 'required' => true, 'label' => 'Name'],
        ],
        'custom_fields' => [],
        'relations' => [],
    ]);

    $lifecycle = <<<PHP
<?php
declare(strict_types=1);
namespace Xestify\plugins\\{$pluginName};
use PDO;
use Xestify\plugins\contracts\PluginLifecycleUpdateInterface;
final class Lifecycle implements PluginLifecycleUpdateInterface {
    public function __construct(private PDO \$pdo) {}
    public function onInstall(): void {}
    public function onActivate(): void { \$GLOBALS['rename_update_activated'] = true; }
    public function onDeactivate(): void {}
    public function onUpdate(array \$context): void { \$GLOBALS['rename_update_seen'] = \$context['to_version'] ?? null; }
    public function onRollback(array \$context): void {}
}
PHP;
    $root = createPluginFixture([
        'name' => $pluginName,
        'label' => 'Renamed Update Plugin',
        'version' => UPDATE_VERSION_2_0,
        'type' => 'entity',
        'core_version' => UPDATE_VERSION_1_0,
    ], false, false, [
        'fields' => [
            'name' => ['type' => 'string', 'required' => true, 'label' => 'Name'],
        ],
        'custom_fields' => [],
        'relations' => [],
    ], $lifecycle);

    try {
        $GLOBALS['rename_update_seen'] = null;
        $GLOBALS['rename_update_activated'] = false;

        $service = buildPluginUpdateService($root, $pdo);
        $result = $service->update($renamedSlug);

        assertEquals(UPDATE_VERSION_2_0, $result['update']['to_version'], 'Should report the disk version resolved by plugin_name');
        assertEquals(UPDATE_VERSION_2_0, $GLOBALS['rename_update_seen'] ?? null, 'Lifecycle.php on disk (found via plugin_name) must receive onUpdate');
        assertTrue($GLOBALS['rename_update_activated'] === true, 'onActivate must fire for an already-active renamed plugin');

        $stmt = $pdo->prepare("SELECT slug, manifest_json->>'version' AS version FROM plugins WHERE manifest_json->>'name' = :plugin_name");
        $stmt->execute([':plugin_name' => $pluginName]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        assertEquals($renamedSlug, (string) ($row['slug'] ?? ''), 'slug must remain the renamed value, unrelated to the disk folder name');
        assertEquals(UPDATE_VERSION_2_0, (string) ($row['version'] ?? ''), 'Installed version should be upgraded');
    } finally {
        cleanupPluginRecord($pdo, $pluginName);
        removePluginFixture($root);
    }
});

echo str_repeat('-', 40) . "\n";
TestSuite::summary();
exit(TestSuite::exitCode());
