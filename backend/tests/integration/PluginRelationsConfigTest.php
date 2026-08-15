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
    echo "[SKIP] PostgreSQL not reachable — all PluginRelationsConfigTest cases skipped.\n";
    echo "       Configure backend/.env with valid DB_* vars and run migrations.\n";
    echo str_repeat('-', 40) . "\n";
    echo "Resultado: 0 passed, 0 failed (skipped)\n";
    exit(0);
}

const RELATIONS_TEST_VERSION = '1.0.0';

/**
 * Inserts an active entity plugin directly (no disk fixture needed) — only
 * used as the "target" entity a relation points at; its own config is not
 * under test here.
 *
 * @param array<string, array<string, mixed>> $identities
 */
function insertActiveEntityPlugin(PDO $pdo, string $slug, array $identities): void
{
    $manifest = [
        'name' => $slug,
        'label' => $slug,
        'version' => RELATIONS_TEST_VERSION,
        'type' => 'entity',
        'core_version' => RELATIONS_TEST_VERSION,
        'description' => '',
    ];
    $schema = [
        'identities' => $identities,
        'fields' => ['name' => ['type' => 'string', 'required' => true, 'label' => 'Name']],
        'custom_fields' => [],
        'relations' => [],
    ];

    $stmt = $pdo->prepare(
        "INSERT INTO plugins (slug, status, manifest_json, schema_json)
         VALUES (:slug, 'active', CAST(:manifest AS jsonb), CAST(:schema AS jsonb))"
    );
    $stmt->execute([
        ':slug' => $slug,
        ':manifest' => json_encode($manifest, JSON_UNESCAPED_UNICODE),
        ':schema' => json_encode($schema, JSON_UNESCAPED_UNICODE),
    ]);
}

echo str_repeat('-', 40) . "\n";

TestSuite::run('saveConfig() persists valid relations and returns them in the config response', function () use ($pdo): void {
    $targetSlug = 'test_relations_target_' . bin2hex(random_bytes(3));
    $sourceSlug = 'test_relations_source_' . bin2hex(random_bytes(3));
    $root = createPluginFixture([
        'name' => $sourceSlug,
        'label' => 'Relations Source',
        'version' => RELATIONS_TEST_VERSION,
        'type' => 'entity',
        'core_version' => RELATIONS_TEST_VERSION,
    ]);

    try {
        insertActiveEntityPlugin($pdo, $targetSlug, ['id' => ['type' => 'uuid', 'auto_generated' => true, 'editable' => false]]);

        $service = buildPluginAdministrationService($root, $pdo);
        $service->registerNew($sourceSlug, []);

        $result = $service->saveConfig($sourceSlug, [
            'fields' => [
                ['active' => true, 'key' => 'name', 'type' => 'string', 'label' => 'Name', 'required' => true, 'summaryView' => true],
            ],
            'relations' => [
                ['key' => 'id_target', 'target_entity' => $targetSlug, 'target_field' => 'id', 'label' => 'Target', 'required' => true],
            ],
        ]);

        assertEquals(1, count($result['config']['relations'] ?? []), 'must return exactly one relation');
        $relation = $result['config']['relations'][0];
        assertEquals('id_target', $relation['key'] ?? null, 'relation key must round-trip');
        assertEquals('belongs_to', $relation['type'] ?? null, 'relation type must always be belongs_to');
        assertEquals($targetSlug, $relation['target_entity'] ?? null, 'relation target_entity must round-trip');
        assertEquals('id', $relation['target_field'] ?? null, 'relation target_field must round-trip');
        assertTrue($relation['required'] === true, 'relation required must round-trip');

        $row = $pdo->prepare('SELECT schema_json FROM plugins WHERE slug = :slug');
        $row->execute([':slug' => $sourceSlug]);
        $schema = json_decode((string) $row->fetchColumn(), true);
        assertEquals(1, count($schema['relations'] ?? []), 'relations must be persisted in schema_json');
    } finally {
        cleanupPluginRecord($pdo, $sourceSlug);
        cleanupPluginRecord($pdo, $targetSlug);
        removePluginFixture($root);
    }
});

TestSuite::run('saveConfig() rejects a relation targeting an entity that is not active', function () use ($pdo): void {
    $sourceSlug = 'test_relations_badtarget_' . bin2hex(random_bytes(3));
    $root = createPluginFixture([
        'name' => $sourceSlug,
        'label' => 'Relations Bad Target',
        'version' => RELATIONS_TEST_VERSION,
        'type' => 'entity',
        'core_version' => RELATIONS_TEST_VERSION,
    ]);

    try {
        $service = buildPluginAdministrationService($root, $pdo);
        $service->registerNew($sourceSlug, []);

        $threw = false;
        try {
            $service->saveConfig($sourceSlug, [
                'fields' => [
                    ['active' => true, 'key' => 'name', 'type' => 'string', 'label' => 'Name', 'required' => true, 'summaryView' => true],
                ],
                'relations' => [
                    ['key' => 'id_ghost', 'target_entity' => 'nonexistent_entity_xyz', 'target_field' => 'id', 'label' => 'Ghost'],
                ],
            ]);
        } catch (InvalidArgumentException) {
            $threw = true;
        }

        assertTrue($threw, 'saveConfig() must reject a relation pointing at an inactive/nonexistent entity');
    } finally {
        cleanupPluginRecord($pdo, $sourceSlug);
        removePluginFixture($root);
    }
});

TestSuite::run('saveConfig() rejects a relation with a target_field that is not a declared identity', function () use ($pdo): void {
    $targetSlug = 'test_relations_badfield_target_' . bin2hex(random_bytes(3));
    $sourceSlug = 'test_relations_badfield_source_' . bin2hex(random_bytes(3));
    $root = createPluginFixture([
        'name' => $sourceSlug,
        'label' => 'Relations Bad Field',
        'version' => RELATIONS_TEST_VERSION,
        'type' => 'entity',
        'core_version' => RELATIONS_TEST_VERSION,
    ]);

    try {
        insertActiveEntityPlugin($pdo, $targetSlug, ['id' => ['type' => 'uuid', 'auto_generated' => true, 'editable' => false]]);

        $service = buildPluginAdministrationService($root, $pdo);
        $service->registerNew($sourceSlug, []);

        $threw = false;
        try {
            $service->saveConfig($sourceSlug, [
                'fields' => [
                    ['active' => true, 'key' => 'name', 'type' => 'string', 'label' => 'Name', 'required' => true, 'summaryView' => true],
                ],
                'relations' => [
                    ['key' => 'id_target', 'target_entity' => $targetSlug, 'target_field' => 'not_a_real_identity', 'label' => 'Target'],
                ],
            ]);
        } catch (InvalidArgumentException) {
            $threw = true;
        }

        assertTrue($threw, 'saveConfig() must reject a target_field that is not a declared identity of the target entity');
    } finally {
        cleanupPluginRecord($pdo, $sourceSlug);
        cleanupPluginRecord($pdo, $targetSlug);
        removePluginFixture($root);
    }
});

TestSuite::run('saveConfig() rejects a relation key that collides with an existing field key', function () use ($pdo): void {
    $targetSlug = 'test_relations_collision_target_' . bin2hex(random_bytes(3));
    $sourceSlug = 'test_relations_collision_source_' . bin2hex(random_bytes(3));
    $root = createPluginFixture([
        'name' => $sourceSlug,
        'label' => 'Relations Collision',
        'version' => RELATIONS_TEST_VERSION,
        'type' => 'entity',
        'core_version' => RELATIONS_TEST_VERSION,
    ]);

    try {
        insertActiveEntityPlugin($pdo, $targetSlug, ['id' => ['type' => 'uuid', 'auto_generated' => true, 'editable' => false]]);

        $service = buildPluginAdministrationService($root, $pdo);
        $service->registerNew($sourceSlug, []);

        $threw = false;
        try {
            $service->saveConfig($sourceSlug, [
                'fields' => [
                    ['active' => true, 'key' => 'name', 'type' => 'string', 'label' => 'Name', 'required' => true, 'summaryView' => true],
                ],
                'relations' => [
                    ['key' => 'name', 'target_entity' => $targetSlug, 'target_field' => 'id', 'label' => 'Name clash'],
                ],
            ]);
        } catch (InvalidArgumentException) {
            $threw = true;
        }

        assertTrue($threw, "saveConfig() must reject a relation key that collides with an existing field key ('name')");
    } finally {
        cleanupPluginRecord($pdo, $sourceSlug);
        cleanupPluginRecord($pdo, $targetSlug);
        removePluginFixture($root);
    }
});

echo str_repeat('-', 40) . "\n";
TestSuite::summary();
exit(TestSuite::exitCode());
