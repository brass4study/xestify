<?php

declare(strict_types=1);

/**
 * ReverseRelationTest — Integration tests for the reverse relation tab
 * (STORY 10.3 §9): when plugin B declares a schema.relations[] entry
 * targeting entity A (§8), GET /entities/{A}/tabs must surface a tab for
 * it, and GET /entities/{B}/records?field=...&value=... must return only
 * B's records pointing at a given A record.
 */

define('BASE_PATH', dirname(__DIR__, 2));

require_once BASE_PATH . '/tests/unit/helpers.php';
require_once BASE_PATH . '/tests/helpers/autoload.php';
require_once BASE_PATH . '/tests/helpers/plugins/plugin_fixtures.php';
require_once BASE_PATH . '/tests/unit/validation_bootstrap.php';
require_once BASE_PATH . '/src/core/Request.php';
require_once BASE_PATH . '/src/controllers/EntityController.php';

use Xestify\controllers\EntityController;
use Xestify\controllers\ExtensionPluginDataStore;
use Xestify\core\Database;
use Xestify\core\HookDispatcher;
use Xestify\core\Request;
use Xestify\exceptions\DatabaseException;
use Xestify\plugins\discovery\PluginSchemaCodec;
use Xestify\plugins\schema\ReverseRelationTabResolver;
use Xestify\repositories\GenericRepository;
use Xestify\repositories\PluginRepository;
use Xestify\services\EntityService;
use Xestify\services\ValidationService;

loadPluginTestEnv(BASE_PATH);

try {
    $pdo = Database::connection();
} catch (DatabaseException) {
    echo "[SKIP] PostgreSQL not reachable — all ReverseRelationTest cases skipped.\n";
    echo "       Configure backend/.env with valid DB_* vars and run php tools/setup/install.php.\n";
    echo str_repeat('-', 40) . "\n";
    echo "Resultado: 0 passed, 0 failed (skipped)\n";
    exit(0);
}

const REVERSE_RELATION_VERSION = '1.0.0';
const MSG_TABS_MUST_SUCCEED = 'tabs() must succeed';

/**
 * @param array<int, array<string, mixed>> $relations
 */
function insertReverseRelationPlugin(PDO $pdo, string $slug, array $relations = []): void
{
    $manifest = [
        'name' => $slug,
        'label' => $slug,
        'version' => REVERSE_RELATION_VERSION,
        'type' => 'entity',
        'core_version' => REVERSE_RELATION_VERSION,
        'description' => '',
    ];
    $schema = [
        'identities' => ['id' => ['type' => 'uuid', 'auto_generated' => true, 'editable' => false]],
        'fields' => ['title' => ['type' => 'string', 'required' => true, 'label' => 'Title']],
        'custom_fields' => [],
        'relations' => $relations,
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

function insertRecord(PDO $pdo, string $entitySlug, array $content): string
{
    $stmt = $pdo->prepare(
        "INSERT INTO plugin_entity_data (entity_slug, content) VALUES (:slug, CAST(:content AS jsonb)) RETURNING id"
    );
    $stmt->execute([':slug' => $entitySlug, ':content' => json_encode($content, JSON_UNESCAPED_UNICODE)]);

    return (string) $stmt->fetchColumn();
}

function buildReverseRelationController(): EntityController
{
    $pdo = Database::connection();

    return new EntityController(
        new EntityService(
            new GenericRepository($pdo),
            new ValidationService(),
            $pdo,
            new ExtensionPluginDataStore($pdo),
            new ReverseRelationTabResolver(new PluginRepository($pdo, new PluginSchemaCodec()))
        ),
        $pdo,
        new HookDispatcher(),
        new ReverseRelationTabResolver(new PluginRepository($pdo, new PluginSchemaCodec()))
    );
}

/**
 * @return array<string, mixed>
 */
function callReverseRelationController(EntityController $ctrl, string $method, array $params, array $query = []): array
{
    $request = new Request($query, [], [], $params);
    ob_start();
    $ctrl->$method($params, $request);
    $output = ob_get_clean();
    $decoded = json_decode((string) $output, true);

    return is_array($decoded) ? $decoded : [];
}

function cleanupReverseRelationFixture(PDO $pdo, string $slug): void
{
    $pdo->prepare('DELETE FROM plugin_entity_data WHERE entity_slug = :slug')->execute([':slug' => $slug]);
    $pdo->prepare('DELETE FROM plugins WHERE slug = :slug')->execute([':slug' => $slug]);
}

echo str_repeat('-', 40) . "\n";

TestSuite::run('GET /entities/{target}/tabs includes the reverse relation tab', function () use ($pdo): void {
    $targetSlug = 'test_reverse_target_' . bin2hex(random_bytes(3));
    $sourceSlug = 'test_reverse_source_' . bin2hex(random_bytes(3));

    try {
        insertReverseRelationPlugin($pdo, $targetSlug);
        insertReverseRelationPlugin($pdo, $sourceSlug, [
            ['key' => 'id_target', 'type' => 'belongs_to', 'target_entity' => $targetSlug, 'target_field' => 'id', 'label' => 'Objetivo', 'required' => false],
        ]);

        $ctrl = buildReverseRelationController();
        $result = callReverseRelationController($ctrl, 'tabs', ['slug' => $targetSlug]);

        assertTrue($result['ok'] ?? false, MSG_TABS_MUST_SUCCEED);
        $tabs = $result['data']['tabs'] ?? [];
        assertEquals(1, count($tabs), 'must include exactly one reverse relation tab');
        assertEquals("relation-{$sourceSlug}-id_target", $tabs[0]['id'] ?? null, 'tab id must follow relation-{source}-{key}');
        assertEquals($sourceSlug, $tabs[0]['label'] ?? null, 'tab label must be the source entity label, not the relation field label');
        assertEquals('relation', $tabs[0]['type'] ?? null, 'tab type must be relation');
        assertEquals($sourceSlug, $tabs[0]['source_entity'] ?? null, 'tab source_entity must be the source plugin slug');
        assertEquals('id_target', $tabs[0]['key'] ?? null, 'tab key must be the relation key');
    } finally {
        cleanupReverseRelationFixture($pdo, $sourceSlug);
        cleanupReverseRelationFixture($pdo, $targetSlug);
    }
});

TestSuite::run('GET /entities/{target}/tabs disambiguates with the relation label when the same source has two relations to the same target', function () use ($pdo): void {
    $targetSlug = 'test_reverse_target_' . bin2hex(random_bytes(3));
    $sourceSlug = 'test_reverse_source_' . bin2hex(random_bytes(3));

    try {
        insertReverseRelationPlugin($pdo, $targetSlug);
        insertReverseRelationPlugin($pdo, $sourceSlug, [
            ['key' => 'id_buyer', 'type' => 'belongs_to', 'target_entity' => $targetSlug, 'target_field' => 'id', 'label' => 'Comprador', 'required' => false],
            ['key' => 'id_seller', 'type' => 'belongs_to', 'target_entity' => $targetSlug, 'target_field' => 'id', 'label' => 'Vendedor', 'required' => false],
        ]);

        $ctrl = buildReverseRelationController();
        $result = callReverseRelationController($ctrl, 'tabs', ['slug' => $targetSlug]);

        assertTrue($result['ok'] ?? false, MSG_TABS_MUST_SUCCEED);
        $tabs = $result['data']['tabs'] ?? [];
        assertEquals(2, count($tabs), 'must include one tab per relation');

        $labels = array_column($tabs, 'label');
        assertTrue(in_array("{$sourceSlug} (Comprador)", $labels, true), 'buyer tab must combine entity label and relation label');
        assertTrue(in_array("{$sourceSlug} (Vendedor)", $labels, true), 'seller tab must combine entity label and relation label');
    } finally {
        cleanupReverseRelationFixture($pdo, $sourceSlug);
        cleanupReverseRelationFixture($pdo, $targetSlug);
    }
});

TestSuite::run('GET /entities/{other}/tabs does not include a relation tab pointing elsewhere', function () use ($pdo): void {
    $targetSlug = 'test_reverse_target_' . bin2hex(random_bytes(3));
    $sourceSlug = 'test_reverse_source_' . bin2hex(random_bytes(3));
    $unrelatedSlug = 'test_reverse_unrelated_' . bin2hex(random_bytes(3));

    try {
        insertReverseRelationPlugin($pdo, $targetSlug);
        insertReverseRelationPlugin($pdo, $unrelatedSlug);
        insertReverseRelationPlugin($pdo, $sourceSlug, [
            ['key' => 'id_target', 'type' => 'belongs_to', 'target_entity' => $targetSlug, 'target_field' => 'id', 'label' => 'Objetivo', 'required' => false],
        ]);

        $ctrl = buildReverseRelationController();
        $result = callReverseRelationController($ctrl, 'tabs', ['slug' => $unrelatedSlug]);

        assertTrue($result['ok'] ?? false, MSG_TABS_MUST_SUCCEED);
        assertEquals([], $result['data']['tabs'] ?? null, 'an entity with no relations pointing at it must get an empty tabs list');
    } finally {
        cleanupReverseRelationFixture($pdo, $sourceSlug);
        cleanupReverseRelationFixture($pdo, $unrelatedSlug);
        cleanupReverseRelationFixture($pdo, $targetSlug);
    }
});

TestSuite::run('GET /entities/{source}/records?field=...&value=... returns only matching records', function () use ($pdo): void {
    $targetSlug = 'test_reverse_target_' . bin2hex(random_bytes(3));
    $sourceSlug = 'test_reverse_source_' . bin2hex(random_bytes(3));

    try {
        insertReverseRelationPlugin($pdo, $targetSlug);
        insertReverseRelationPlugin($pdo, $sourceSlug, [
            ['key' => 'id_target', 'type' => 'belongs_to', 'target_entity' => $targetSlug, 'target_field' => 'id', 'label' => 'Objetivo', 'required' => false],
        ]);

        $matchingTargetId = insertRecord($pdo, $targetSlug, ['title' => 'Target A']);
        $otherTargetId = insertRecord($pdo, $targetSlug, ['title' => 'Target B']);
        insertRecord($pdo, $sourceSlug, ['title' => 'Source matching 1', 'id_target' => $matchingTargetId]);
        insertRecord($pdo, $sourceSlug, ['title' => 'Source matching 2', 'id_target' => $matchingTargetId]);
        insertRecord($pdo, $sourceSlug, ['title' => 'Source other', 'id_target' => $otherTargetId]);

        $ctrl = buildReverseRelationController();
        $result = callReverseRelationController($ctrl, 'index', ['slug' => $sourceSlug], ['field' => 'id_target', 'value' => $matchingTargetId]);

        assertTrue($result['ok'] ?? false, 'index() must succeed');
        $records = $result['data'] ?? [];
        assertEquals(2, count($records), 'only the 2 records pointing at the matching target must be returned');
        foreach ($records as $record) {
            $content = json_decode((string) ($record['content'] ?? ''), true);
            assertEquals($matchingTargetId, $content['id_target'] ?? null, 'every returned record must point at the requested target id');
        }
    } finally {
        cleanupReverseRelationFixture($pdo, $sourceSlug);
        cleanupReverseRelationFixture($pdo, $targetSlug);
    }
});

TestSuite::run('GET /entities/{source}/records?field=...&value=... rejects an unknown field', function () use ($pdo): void {
    $sourceSlug = 'test_reverse_source_' . bin2hex(random_bytes(3));

    try {
        insertReverseRelationPlugin($pdo, $sourceSlug);

        $ctrl = buildReverseRelationController();
        $result = callReverseRelationController($ctrl, 'index', ['slug' => $sourceSlug], ['field' => 'not_a_real_field', 'value' => 'x']);

        assertTrue(!($result['ok'] ?? true), 'index() must fail for an unknown field');
        assertEquals(422, $result['error']['code'] ?? null, 'must respond with 422');
    } finally {
        cleanupReverseRelationFixture($pdo, $sourceSlug);
    }
});

echo str_repeat('-', 40) . "\n";
TestSuite::summary();
exit(TestSuite::exitCode());
