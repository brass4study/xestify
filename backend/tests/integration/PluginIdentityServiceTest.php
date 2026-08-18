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
    echo "[SKIP] PostgreSQL not reachable — all PluginIdentityServiceTest cases skipped.\n";
    echo "       Configure backend/.env with valid DB_* vars and run php tools/setup/install.php.\n";
    echo str_repeat('-', 40) . "\n";
    echo "Resultado: 0 passed, 0 failed (skipped)\n";
    exit(0);
}

const IDENTITY_VERSION = '1.0.0';
const INSERT_ENTITY_DATA_SQL = "INSERT INTO plugin_entity_data (entity_slug, content) VALUES (:slug, '{}'::jsonb)";
const COUNT_ENTITY_DATA_BY_SLUG_SQL = 'SELECT COUNT(*) AS cnt FROM plugin_entity_data WHERE entity_slug = :slug';
const SELECT_SLUG_BY_PLUGIN_NAME_SQL = "SELECT slug FROM plugins WHERE manifest_json->>'name' = :plugin_name";

/**
 * @return array<string, mixed> decoded row (manifest_json is a PHP array)
 */
function insertIdentityPlugin(PDO $pdo, string $pluginName, string $slug, string $pluginType, ?string $targetEntity = null): array
{
    $schema = $pluginType === 'extension'
        ? ['fields' => [], 'custom_fields' => [], 'relations' => []]
        : [
            'fields' => ['name' => ['type' => 'string', 'required' => true, 'label' => 'Name']],
            'custom_fields' => [],
            'relations' => [],
        ];

    $manifest = [
        'name' => $pluginName,
        'label' => 'Old Name',
        'version' => IDENTITY_VERSION,
        'type' => $pluginType,
        'core_version' => IDENTITY_VERSION,
        'description' => 'old description',
    ];
    if ($pluginType === 'extension') {
        $manifest['target_entity'] = $targetEntity ?? '*';
    }

    $stmt = $pdo->prepare(
        'INSERT INTO plugins (slug, status, manifest_json, schema_json)
         VALUES (:slug, :status, CAST(:manifest AS jsonb), CAST(:schema AS jsonb))
         RETURNING id, slug, status, manifest_json, schema_json'
    );
    $stmt->execute([
        ':slug' => $slug,
        ':status' => 'active',
        ':manifest' => json_encode($manifest, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ':schema' => json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    ]);

    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row === false) {
        return [];
    }

    $decoded = json_decode((string) $row['manifest_json'], true);
    $row['manifest_json'] = is_array($decoded) ? $decoded : [];

    return $row;
}

function countRows(PDO $pdo, string $sql, array $params): string
{
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return (string) ($stmt->fetch(PDO::FETCH_ASSOC)['cnt'] ?? '0');
}

echo str_repeat('-', 40) . "\n";

TestSuite::run('updateIdentity() updates slug/name/description and keeps plugin_name fixed', function () use ($pdo): void {
    $pluginName = 'test_identity_basic_' . bin2hex(random_bytes(3));
    $newSlug = $pluginName . '_v2';
    $current = insertIdentityPlugin($pdo, $pluginName, $pluginName, 'entity');

    try {
        $service = buildPluginIdentityService($pdo);
        $updated = $service->updateIdentity($current, [
            'slug' => $newSlug,
            'name' => 'New Name',
            'description' => 'New description',
        ]);

        assertEquals($newSlug, (string) $updated['slug'], 'slug should be updated');
        assertEquals('New Name', (string) ($updated['manifest_json']['label'] ?? ''), 'name should be updated');
        assertEquals('New description', (string) ($updated['manifest_json']['description'] ?? ''), 'description should be updated');
        assertEquals($pluginName, (string) ($updated['manifest_json']['name'] ?? ''), 'plugin_name must remain fixed');
    } finally {
        cleanupPluginRecord($pdo, $pluginName);
    }
});

TestSuite::run(
    'updateIdentity() cascades an entity rename to entity data, extension data, dependent target_entity and history (STORY 10.3)',
    function () use ($pdo): void {
        $entityPluginName = 'test_identity_entity_' . bin2hex(random_bytes(3));
        $extPluginName = 'test_identity_ext_' . bin2hex(random_bytes(3));
        $oldSlug = $entityPluginName;
        $newSlug = $entityPluginName . '_renamed';

        $entityPlugin = insertIdentityPlugin($pdo, $entityPluginName, $oldSlug, 'entity');
        insertIdentityPlugin($pdo, $extPluginName, $extPluginName, 'extension', $oldSlug);

        $pdo->prepare(INSERT_ENTITY_DATA_SQL)
            ->execute([':slug' => $oldSlug]);

        $pdo->prepare(
            'INSERT INTO plugin_extension_data (plugin_slug, entity_slug, record_id, content)
             VALUES (:plugin_slug, :entity_slug, gen_random_uuid(), :content::jsonb)'
        )->execute([
            ':plugin_slug' => $extPluginName,
            ':entity_slug' => $oldSlug,
            ':content' => '{}',
        ]);

        $historyManifest = json_encode([
            'name' => $entityPluginName,
            'label' => 'History Snapshot',
            'version' => IDENTITY_VERSION,
            'type' => 'entity',
            'core_version' => IDENTITY_VERSION,
            'description' => '',
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $pdo->prepare(
            "INSERT INTO plugin_update_history (slug, status, manifest_json, target_version)
             VALUES (:slug, 'active', CAST(:manifest AS jsonb), :version)"
        )->execute([':slug' => $oldSlug, ':manifest' => $historyManifest, ':version' => IDENTITY_VERSION]);

        try {
            $service = buildPluginIdentityService($pdo);
            $service->updateIdentity($entityPlugin, ['slug' => $newSlug]);

            assertEquals(
                '1',
                countRows($pdo, COUNT_ENTITY_DATA_BY_SLUG_SQL, [':slug' => $newSlug]),
                'plugin_entity_data.entity_slug should follow the rename'
            );
            assertEquals(
                '0',
                countRows($pdo, COUNT_ENTITY_DATA_BY_SLUG_SQL, [':slug' => $oldSlug]),
                'no plugin_entity_data row should remain under the old slug'
            );

            assertEquals(
                '1',
                countRows(
                    $pdo,
                    'SELECT COUNT(*) AS cnt FROM plugin_extension_data WHERE entity_slug = :slug AND plugin_slug = :plugin_slug',
                    [':slug' => $newSlug, ':plugin_slug' => $extPluginName]
                ),
                'plugin_extension_data.entity_slug should follow the rename'
            );

            $extTarget = $pdo->prepare("SELECT manifest_json->>'target_entity' AS target FROM plugins WHERE manifest_json->>'name' = :plugin_name");
            $extTarget->execute([':plugin_name' => $extPluginName]);
            assertEquals(
                $newSlug,
                (string) ($extTarget->fetch(PDO::FETCH_ASSOC)['target'] ?? ''),
                'the dependent extension plugin target_entity should follow the renamed entity'
            );

            assertEquals(
                '1',
                countRows($pdo, 'SELECT COUNT(*) AS cnt FROM plugin_update_history WHERE slug = :slug', [':slug' => $newSlug]),
                'plugin_update_history.slug should follow the rename'
            );
        } finally {
            cleanupPluginRecord($pdo, $entityPluginName);
            cleanupPluginRecord($pdo, $extPluginName);
            $pdo->prepare('DELETE FROM plugin_entity_data WHERE entity_slug IN (:old, :new)')
                ->execute([':old' => $oldSlug, ':new' => $newSlug]);
            $pdo->prepare('DELETE FROM plugin_extension_data WHERE plugin_slug = :plugin_slug')
                ->execute([':plugin_slug' => $extPluginName]);
        }
    }
);

TestSuite::run(
    "updateIdentity() cascades an extension plugin rename to its own plugin_slug data, not entity_slug (STORY 10.3)",
    function () use ($pdo): void {
        $extPluginName = 'test_identity_extself_' . bin2hex(random_bytes(3));
        $oldSlug = $extPluginName;
        $newSlug = $extPluginName . '_renamed';
        $hostEntitySlug = 'test_identity_host_' . bin2hex(random_bytes(3));

        $extPlugin = insertIdentityPlugin($pdo, $extPluginName, $oldSlug, 'extension', $hostEntitySlug);

        $pdo->prepare(
            'INSERT INTO plugin_extension_data (plugin_slug, entity_slug, record_id, content)
             VALUES (:plugin_slug, :entity_slug, gen_random_uuid(), :content::jsonb)'
        )->execute([
            ':plugin_slug' => $oldSlug,
            ':entity_slug' => $hostEntitySlug,
            ':content' => '{}',
        ]);

        try {
            $service = buildPluginIdentityService($pdo);
            $service->updateIdentity($extPlugin, ['slug' => $newSlug]);

            assertEquals(
                '1',
                countRows(
                    $pdo,
                    'SELECT COUNT(*) AS cnt FROM plugin_extension_data WHERE plugin_slug = :slug AND entity_slug = :entity',
                    [':slug' => $newSlug, ':entity' => $hostEntitySlug]
                ),
                'plugin_extension_data.plugin_slug should follow the rename'
            );
            assertEquals(
                '0',
                countRows($pdo, 'SELECT COUNT(*) AS cnt FROM plugin_extension_data WHERE plugin_slug = :slug', [':slug' => $oldSlug]),
                'no row should remain under the old plugin_slug'
            );
        } finally {
            cleanupPluginRecord($pdo, $extPluginName);
            $pdo->prepare('DELETE FROM plugin_extension_data WHERE entity_slug = :entity')
                ->execute([':entity' => $hostEntitySlug]);
        }
    }
);

TestSuite::run('updateIdentity() rejects an empty slug and leaves the row unchanged', function () use ($pdo): void {
    $pluginName = 'test_identity_empty_' . bin2hex(random_bytes(3));
    $current = insertIdentityPlugin($pdo, $pluginName, $pluginName, 'entity');

    try {
        $service = buildPluginIdentityService($pdo);
        $threw = false;
        try {
            $service->updateIdentity($current, ['slug' => '   ']);
        } catch (InvalidArgumentException) {
            $threw = true;
        }

        assertTrue($threw, 'Empty slug must be rejected');

        $stmt = $pdo->prepare(SELECT_SLUG_BY_PLUGIN_NAME_SQL);
        $stmt->execute([':plugin_name' => $pluginName]);
        assertEquals($pluginName, (string) ($stmt->fetch(PDO::FETCH_ASSOC)['slug'] ?? ''), 'slug must remain unchanged after a rejected rename');
    } finally {
        cleanupPluginRecord($pdo, $pluginName);
    }
});

TestSuite::run('updateIdentity() rejects an invalid slug format', function () use ($pdo): void {
    $pluginName = 'test_identity_badformat_' . bin2hex(random_bytes(3));
    $current = insertIdentityPlugin($pdo, $pluginName, $pluginName, 'entity');

    try {
        $service = buildPluginIdentityService($pdo);
        $threw = false;
        try {
            $service->updateIdentity($current, ['slug' => 'Invalid Slug!']);
        } catch (InvalidArgumentException) {
            $threw = true;
        }

        assertTrue($threw, 'A slug with invalid characters must be rejected');
    } finally {
        cleanupPluginRecord($pdo, $pluginName);
    }
});

TestSuite::run('updateIdentity() rejects a duplicate slug and leaves both rows unchanged (STORY 10.3)', function () use ($pdo): void {
    $pluginNameA = 'test_identity_dupa_' . bin2hex(random_bytes(3));
    $pluginNameB = 'test_identity_dupb_' . bin2hex(random_bytes(3));

    $currentA = insertIdentityPlugin($pdo, $pluginNameA, $pluginNameA, 'entity');
    insertIdentityPlugin($pdo, $pluginNameB, $pluginNameB, 'entity');

    try {
        $service = buildPluginIdentityService($pdo);
        $threw = false;
        try {
            $service->updateIdentity($currentA, ['slug' => $pluginNameB]);
        } catch (InvalidArgumentException) {
            $threw = true;
        }

        assertTrue($threw, 'Renaming to an already-used slug must be rejected');

        $stmt = $pdo->prepare(SELECT_SLUG_BY_PLUGIN_NAME_SQL);
        $stmt->execute([':plugin_name' => $pluginNameA]);
        assertEquals($pluginNameA, (string) ($stmt->fetch(PDO::FETCH_ASSOC)['slug'] ?? ''), 'slug A must remain unchanged after a rejected rename');
    } finally {
        cleanupPluginRecord($pdo, $pluginNameA);
        cleanupPluginRecord($pdo, $pluginNameB);
    }
});

TestSuite::run('updateIdentity() only updates name/description without cascading when the slug does not change', function () use ($pdo): void {
    $pluginName = 'test_identity_partial_' . bin2hex(random_bytes(3));
    $current = insertIdentityPlugin($pdo, $pluginName, $pluginName, 'entity');

    $pdo->prepare(INSERT_ENTITY_DATA_SQL)
        ->execute([':slug' => $pluginName]);

    try {
        $service = buildPluginIdentityService($pdo);
        $updated = $service->updateIdentity($current, ['name' => 'Renamed Only']);

        assertEquals($pluginName, (string) $updated['slug'], 'slug must stay the same when absent from the payload');
        assertEquals('Renamed Only', (string) ($updated['manifest_json']['label'] ?? ''), 'name should still be updated');
        assertEquals(
            '1',
            countRows($pdo, COUNT_ENTITY_DATA_BY_SLUG_SQL, [':slug' => $pluginName]),
            'entity data must remain untouched when the slug is unchanged'
        );
    } finally {
        cleanupPluginRecord($pdo, $pluginName);
        $pdo->prepare('DELETE FROM plugin_entity_data WHERE entity_slug = :slug')->execute([':slug' => $pluginName]);
    }
});

TestSuite::run(
    'PluginAdministrationService::saveConfig() rolls back the slug rename when the fields payload is invalid (STORY 10.3 atomicity)',
    function () use ($pdo): void {
        $pluginName = 'test_identity_atomic_' . bin2hex(random_bytes(3));
        $newSlug = $pluginName . '_renamed';
        insertIdentityPlugin($pdo, $pluginName, $pluginName, 'entity');

        $pdo->prepare(INSERT_ENTITY_DATA_SQL)
            ->execute([':slug' => $pluginName]);

        try {
            $service = buildPluginAdministrationService(sys_get_temp_dir(), $pdo);

            $threw = false;
            try {
                // Valid slug rename + a fields payload missing the mandatory
                // base field 'name' — the identity rename (and its cascade)
                // happens first, the fields validation fails afterward; both
                // must roll back together as a single transaction.
                $service->saveConfig($pluginName, [
                    'slug' => $newSlug,
                    'fields' => [],
                ]);
            } catch (InvalidArgumentException) {
                $threw = true;
            }

            assertTrue($threw, 'Missing base field must abort the whole saveConfig() transaction');
            assertFalse($pdo->inTransaction(), 'The transaction must not be left open after rollback');

            $stmt = $pdo->prepare(SELECT_SLUG_BY_PLUGIN_NAME_SQL);
            $stmt->execute([':plugin_name' => $pluginName]);
            assertEquals(
                $pluginName,
                (string) ($stmt->fetch(PDO::FETCH_ASSOC)['slug'] ?? ''),
                'slug rename must roll back together with the invalid fields payload'
            );

            assertEquals(
                '1',
                countRows($pdo, COUNT_ENTITY_DATA_BY_SLUG_SQL, [':slug' => $pluginName]),
                'the entity data cascade must roll back to the old slug'
            );
        } finally {
            cleanupPluginRecord($pdo, $pluginName);
            $pdo->prepare('DELETE FROM plugin_entity_data WHERE entity_slug IN (:old, :new)')
                ->execute([':old' => $pluginName, ':new' => $newSlug]);
        }
    }
);

echo str_repeat('-', 40) . "\n";
TestSuite::summary();
exit(TestSuite::exitCode());
