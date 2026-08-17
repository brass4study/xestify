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
    echo "[SKIP] PostgreSQL not reachable — all PluginFieldsConfigTest cases skipped.\n";
    echo "       Configure backend/.env with valid DB_* vars and run migrations.\n";
    echo str_repeat('-', 40) . "\n";
    echo "Resultado: 0 passed, 0 failed (skipped)\n";
    exit(0);
}

const FIELDS_TEST_VERSION = '1.0.0';

echo str_repeat('-', 40) . "\n";

TestSuite::run('saveConfig() persists a summaryView change on a base entity field without touching its locked type/label/required', function () use ($pdo): void {
    $sourceSlug = 'test_fields_base_summary_' . bin2hex(random_bytes(3));
    $root = createPluginFixture([
        'name' => $sourceSlug,
        'label' => 'Fields Base Summary',
        'version' => FIELDS_TEST_VERSION,
        'type' => 'entity',
        'core_version' => FIELDS_TEST_VERSION,
    ]);

    try {
        $service = buildPluginAdministrationService($root, $pdo);
        $service->registerNew($sourceSlug, []);

        $result = $service->saveConfig($sourceSlug, [
            'fields' => [
                ['active' => true, 'key' => 'name', 'type' => 'string', 'label' => 'Name', 'required' => true, 'summaryView' => false],
            ],
        ]);

        $fieldsByKey = [];
        foreach ($result['config']['fields'] as $field) {
            $fieldsByKey[$field['key']] = $field;
        }
        assertTrue($fieldsByKey['name']['summaryView'] === false, 'a base field summaryView change must round-trip in the saveConfig() response');

        $row = $pdo->prepare('SELECT schema_json FROM plugins WHERE slug = :slug');
        $row->execute([':slug' => $sourceSlug]);
        $schema = json_decode((string) $row->fetchColumn(), true);
        assertTrue($schema['fields']['name']['summaryView'] === false, 'the base field summaryView change must be persisted in schema_json, not silently dropped');
        assertEquals('string', $schema['fields']['name']['type'] ?? null, 'the base field type must remain untouched');
        assertEquals('Name', $schema['fields']['name']['label'] ?? null, 'the base field label must remain untouched');

        $reloaded = $service->getConfig($sourceSlug);
        $reloadedByKey = [];
        foreach ($reloaded['config']['fields'] as $field) {
            $reloadedByKey[$field['key']] = $field;
        }
        assertTrue($reloadedByKey['name']['summaryView'] === false, 'a fresh getConfig() must reflect the persisted summaryView change');
    } finally {
        cleanupPluginRecord($pdo, $sourceSlug);
        removePluginFixture($root);
    }
});

TestSuite::run('saveConfig() persists a summaryView change on a locked (non-additional) extension field across repeated saves', function () use ($pdo): void {
    $sourceSlug = 'test_fields_ext_summary_' . bin2hex(random_bytes(3));
    $root = createPluginFixture(
        [
            'name' => $sourceSlug,
            'label' => 'Fields Extension Summary',
            'version' => FIELDS_TEST_VERSION,
            'type' => 'extension',
            'core_version' => FIELDS_TEST_VERSION,
            'target_entity' => '*',
        ],
        false,
        false,
        ['fields' => ['note' => ['type' => 'text', 'required' => false, 'label' => 'Note', 'editable' => false]]]
    );

    try {
        $service = buildPluginAdministrationService($root, $pdo);
        $service->registerNew($sourceSlug, []);

        $first = $service->saveConfig($sourceSlug, [
            'fields' => [
                ['active' => true, 'key' => 'note', 'type' => 'text', 'label' => 'Note', 'required' => false, 'summaryView' => false],
            ],
        ]);
        $firstByKey = [];
        foreach ($first['config']['fields'] as $field) {
            $firstByKey[$field['key']] = $field;
        }
        assertTrue($firstByKey['note']['summaryView'] === false, 'first save must persist summaryView:false on the locked field');

        // A second save with a different summaryView value must win over
        // whatever got written to disk on the first save — this is the
        // exact scenario mergeFieldDefinition()'s stale-metadata copy loop
        // used to clobber (it treated summaryView like an author-owned flag
        // such as 'editable', instead of a payload-controlled one).
        $second = $service->saveConfig($sourceSlug, [
            'fields' => [
                ['active' => true, 'key' => 'note', 'type' => 'text', 'label' => 'Note', 'required' => false, 'summaryView' => true],
            ],
        ]);
        $secondByKey = [];
        foreach ($second['config']['fields'] as $field) {
            $secondByKey[$field['key']] = $field;
        }
        assertTrue($secondByKey['note']['summaryView'] === true, 'a second save must let a new summaryView value win over the previously persisted one');

        $row = $pdo->prepare('SELECT schema_json FROM plugins WHERE slug = :slug');
        $row->execute([':slug' => $sourceSlug]);
        $schema = json_decode((string) $row->fetchColumn(), true);
        assertTrue($schema['fields']['note']['summaryView'] === true, 'the second summaryView value must be persisted in schema_json');
        assertTrue($schema['fields']['note']['editable'] === false, 'other author-owned metadata (editable) must survive untouched');
    } finally {
        cleanupPluginRecord($pdo, $sourceSlug);
        removePluginFixture($root);
    }
});

echo str_repeat('-', 40) . "\n";
TestSuite::summary();
exit(TestSuite::exitCode());
