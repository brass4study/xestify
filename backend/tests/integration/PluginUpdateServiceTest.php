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
    echo "       Configure backend/.env with valid DB_* vars and run migrations.\n";
    echo str_repeat('-', 40) . "\n";
    echo "Resultado: 0 passed, 0 failed (skipped)\n";
    exit(0);
}

const UPDATE_SLUG_PARAM = ':slug';
const UPDATE_VERSION_1_0 = '1.0.0';
const UPDATE_VERSION_1_1 = '1.1.0';
const UPDATE_VERSION_2_0 = '2.0.0';
const MSG_VERSION_SHOULD_BE_UPDATED = 'Version should be updated';

echo str_repeat('-', 40) . "\n";

TestSuite::run('update() upgrades plugin version without schema changes and stores snapshot', function () use ($pdo): void {
    $slug = 'test_update_ver_' . bin2hex(random_bytes(3));
    $pdo->prepare(
        "INSERT INTO plugins (slug, name, plugin_type, version, status, schema_version, schema_json)
         VALUES (:slug, 'Versioned Plugin', 'entity', '1.0.0', 'inactive', 1, CAST(:schema AS jsonb))"
    )->execute([
        ':slug' => $slug,
        ':schema' => json_encode([
            'entity' => $slug,
            'version' => UPDATE_VERSION_1_0,
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
        'version' => UPDATE_VERSION_2_0,
        'type' => 'entity',
        'core_version' => UPDATE_VERSION_1_0,
    ];
    $schema = [
        'entity' => $slug,
        'version' => UPDATE_VERSION_2_0,
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

        $stmt = $pdo->prepare('SELECT version, schema_version FROM plugins WHERE slug = :slug');
        $stmt->execute([UPDATE_SLUG_PARAM => $slug]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        assertEquals(UPDATE_VERSION_2_0, (string) ($row['version'] ?? ''), 'Installed version should be upgraded');
        assertEquals('1', (string) ($row['schema_version'] ?? ''), 'schema_version should not change');

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

TestSuite::run('update() merges additive schema changes and increments schema version', function () use ($pdo): void {
    $slug = 'test_update_schema_' . bin2hex(random_bytes(3));
    $pdo->prepare(
        "INSERT INTO plugins (slug, name, plugin_type, version, status, schema_version, schema_json)
         VALUES (:slug, 'Schema Plugin', 'entity', '1.0.0', 'inactive', 2, CAST(:schema AS jsonb))"
    )->execute([
        ':slug' => $slug,
        ':schema' => json_encode([
            'entity' => $slug,
            'version' => UPDATE_VERSION_1_0,
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
        'version' => UPDATE_VERSION_1_1,
        'type' => 'entity',
        'core_version' => UPDATE_VERSION_1_0,
    ];
    $schema = [
        'entity' => $slug,
        'version' => UPDATE_VERSION_1_1,
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

        $stmt = $pdo->prepare('SELECT version, schema_version, schema_json FROM plugins WHERE slug = :slug');
        $stmt->execute([UPDATE_SLUG_PARAM => $slug]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $decoded = json_decode((string) ($row['schema_json'] ?? '{}'), true);

        assertEquals(UPDATE_VERSION_1_1, (string) ($row['version'] ?? ''), MSG_VERSION_SHOULD_BE_UPDATED);
        assertEquals('3', (string) ($row['schema_version'] ?? ''), 'schema_version should increment');
        assertTrue(isset($decoded['fields']['email']), 'New field should be merged into live schema');
    } finally {
        cleanupPluginRecord($pdo, $slug);
        removePluginFixture($root);
    }
});

TestSuite::run('update() merges additive schema changes for extension plugins', function () use ($pdo): void {
    $slug = 'test_update_ext_' . bin2hex(random_bytes(3));
    $pdo->prepare(
        "INSERT INTO plugins (slug, name, plugin_type, version, status, schema_version, schema_json)
         VALUES (:slug, 'Extension Plugin', 'extension', '1.0.0', 'inactive', 1, CAST(:schema AS jsonb))"
    )->execute([
        ':slug' => $slug,
        ':schema' => json_encode([
            'plugin' => $slug,
            'version' => UPDATE_VERSION_1_0,
            'target_entity' => '*',
            'fields' => [
                'body' => ['type' => 'text', 'required' => true, 'label' => 'Body'],
            ],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    ]);

    $manifest = [
        'slug' => $slug,
        'name' => 'Extension Plugin',
        'version' => UPDATE_VERSION_1_1,
        'type' => 'extension',
        'core_version' => UPDATE_VERSION_1_0,
    ];
    $root = createPluginFixture($manifest);
    file_put_contents($root . '/' . $slug . '/schema.json', (string) json_encode([
        'plugin' => $slug,
        'version' => UPDATE_VERSION_1_1,
        'target_entity' => '*',
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

        $stmt = $pdo->prepare('SELECT version, schema_json FROM plugins WHERE slug = :slug');
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
    $pdo->prepare(
        "INSERT INTO plugins (slug, name, plugin_type, version, status, schema_version, schema_json)
         VALUES (:slug, 'Configured Plugin', 'entity', '1.0.0', 'inactive', 2, CAST(:schema AS jsonb))"
    )->execute([
        ':slug' => $slug,
        ':schema' => json_encode([
            'entity' => $slug,
            'version' => UPDATE_VERSION_1_0,
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
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    ]);

    $manifest = [
        'slug' => $slug,
        'name' => 'Configured Plugin',
        'version' => UPDATE_VERSION_1_1,
        'type' => 'entity',
        'core_version' => UPDATE_VERSION_1_0,
    ];
    $schema = [
        'entity' => $slug,
        'version' => UPDATE_VERSION_1_1,
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

        assertTrue($result['update']['schema_changed'] === true, 'A new suggested field should still bump schema_version');
        assertTrue(
            in_array('stock', $result['update']['diff']['custom_fields']['added'], true),
            'diff should report stock as added to the catalog'
        );

        $stmt = $pdo->prepare('SELECT version, schema_json FROM plugins WHERE slug = :slug');
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
    $pdo->prepare(
        "INSERT INTO plugins (slug, name, plugin_type, version, status, schema_version, schema_json)
         VALUES (:slug, 'Same Version', 'entity', :version, 'inactive', 1, CAST(:schema AS jsonb))"
    )->execute([
        ':slug' => $slug,
        ':version' => UPDATE_VERSION_1_0,
        ':schema' => json_encode([
            'entity' => $slug,
            'fields' => [
                'name' => ['type' => 'string', 'required' => true, 'label' => 'Name'],
            ],
            'custom_fields' => [],
            'relations' => [],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    ]);

    $root = createPluginFixture([
        'slug' => $slug,
        'name' => 'Same Version',
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
    $pdo->prepare(
        "INSERT INTO plugins (slug, name, plugin_type, version, status, schema_version, schema_json)
         VALUES (:slug, 'Breaking Plugin', 'entity', '1.0.0', 'inactive', 1, CAST(:schema AS jsonb))"
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

    $schema = [
        'entity' => $slug,
        'version' => UPDATE_VERSION_1_1,
        'fields' => [
            'name' => ['type' => 'text', 'required' => true, 'label' => 'Name'],
        ],
        'custom_fields' => [],
        'relations' => [],
    ];
    $root = createPluginFixture([
        'slug' => $slug,
        'name' => 'Breaking Plugin',
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
    $pdo->prepare(
        "INSERT INTO plugins (slug, name, plugin_type, version, status, schema_version, schema_json)
         VALUES (:slug, 'Corrupt Installed', 'entity', '1.0.0', 'inactive', 26, CAST(:schema AS jsonb))"
    )->execute([
        ':slug' => $slug,
        ':schema' => json_encode([
            'fields' => [
                'name' => ['type' => 'string', 'required' => true],
            ],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    ]);

    $schema = [
        'entity' => $slug,
        'version' => UPDATE_VERSION_1_1,
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
        'slug' => $slug,
        'name' => 'Corrupt Installed',
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

        $stmt = $pdo->prepare('SELECT version, schema_version, schema_json FROM plugins WHERE slug = :slug');
        $stmt->execute([UPDATE_SLUG_PARAM => $slug]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $decoded = json_decode((string) ($row['schema_json'] ?? '{}'), true);

        assertEquals(UPDATE_VERSION_1_0, (string) ($row['version'] ?? ''), 'Version must remain unchanged');
        assertEquals('26', (string) ($row['schema_version'] ?? ''), 'schema_version must remain unchanged');
        assertTrue(!isset($decoded['fields']['email']), 'Corrupt schema must not be repaired by update');
    } finally {
        cleanupPluginRecord($pdo, $slug);
        removePluginFixture($root);
    }
});

TestSuite::run('update() rolls back plugin and snapshot when onUpdate fails', function () use ($pdo): void {
    $slug = 'test_update_rollback_' . bin2hex(random_bytes(3));
    $pdo->prepare(
        "INSERT INTO plugins (slug, name, plugin_type, version, status, schema_version, schema_json)
         VALUES (:slug, 'Rollback Plugin', 'entity', '1.0.0', 'active', 1, CAST(:schema AS jsonb))"
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

    $schema = [
        'entity' => $slug,
        'version' => UPDATE_VERSION_2_0,
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
        'slug' => $slug,
        'name' => 'Rollback Plugin',
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

        $stmt = $pdo->prepare('SELECT version, status, schema_version, schema_json FROM plugins WHERE slug = :slug');
        $stmt->execute([UPDATE_SLUG_PARAM => $slug]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $decoded = json_decode((string) ($row['schema_json'] ?? '{}'), true);

        assertEquals(UPDATE_VERSION_1_0, (string) ($row['version'] ?? ''), 'Version must roll back');
        assertEquals('active', (string) ($row['status'] ?? ''), 'Status must roll back');
        assertEquals('1', (string) ($row['schema_version'] ?? ''), 'schema_version must roll back');
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

echo str_repeat('-', 40) . "\n";
TestSuite::summary();
exit(TestSuite::exitCode());
