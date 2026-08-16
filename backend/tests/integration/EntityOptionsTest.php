<?php

declare(strict_types=1);

/**
 * EntityOptionsTest — Integration tests for the lightweight relation-picker
 * endpoint: GET /entities/{slug}/options returns {id, label} pairs (no full
 * `content`, no pagination) used to populate relation <select> fields on
 * OTHER entities' EntityEdit forms. The label concatenates the values of
 * fields/custom_fields counted as "summary" (summaryView !== false),
 * fields first then custom_fields, falling back to the record id when none
 * qualify.
 */

define('BASE_PATH', dirname(__DIR__, 2));

require_once BASE_PATH . '/tests/unit/helpers.php';
require_once BASE_PATH . '/tests/helpers/autoload.php';
require_once BASE_PATH . '/tests/helpers/plugins/plugin_fixtures.php';
require_once BASE_PATH . '/tests/unit/validation_bootstrap.php';
require_once BASE_PATH . '/src/core/Request.php';
require_once BASE_PATH . '/src/controllers/EntityController.php';

use Xestify\controllers\EntityController;
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
    echo "[SKIP] PostgreSQL not reachable — all EntityOptionsTest cases skipped.\n";
    echo "       Configure backend/.env with valid DB_* vars and run migrations.\n";
    echo str_repeat('-', 40) . "\n";
    echo "Resultado: 0 passed, 0 failed (skipped)\n";
    exit(0);
}

const ENTITY_OPTIONS_VERSION = '1.0.0';

/**
 * @param array<string, mixed> $fields
 * @param array<int, array<string, mixed>> $customFields
 */
function insertOptionsEntityPlugin(PDO $pdo, string $slug, array $fields, array $customFields = []): void
{
    $manifest = [
        'name' => $slug,
        'label' => $slug,
        'version' => ENTITY_OPTIONS_VERSION,
        'type' => 'entity',
        'core_version' => ENTITY_OPTIONS_VERSION,
        'description' => '',
    ];
    $schema = [
        'identities' => ['id' => ['type' => 'uuid', 'auto_generated' => true, 'editable' => false]],
        'fields' => $fields,
        'custom_fields' => $customFields,
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

function insertOptionsRecord(PDO $pdo, string $entitySlug, array $content): string
{
    $stmt = $pdo->prepare(
        "INSERT INTO plugin_entity_data (entity_slug, content) VALUES (:slug, CAST(:content AS jsonb)) RETURNING id"
    );
    $stmt->execute([':slug' => $entitySlug, ':content' => json_encode($content, JSON_UNESCAPED_UNICODE)]);

    return (string) $stmt->fetchColumn();
}

function buildOptionsController(): EntityController
{
    $pdo = Database::connection();

    return new EntityController(
        new EntityService(new GenericRepository($pdo), new ValidationService(), $pdo),
        $pdo,
        new HookDispatcher(),
        new ReverseRelationTabResolver(new PluginRepository($pdo, new PluginSchemaCodec()))
    );
}

/**
 * @return array<string, mixed>
 */
function callOptionsController(EntityController $ctrl, array $params): array
{
    $request = new Request([], [], [], $params);
    ob_start();
    $ctrl->options($params, $request);
    $output = ob_get_clean();
    $decoded = json_decode((string) $output, true);

    return is_array($decoded) ? $decoded : [];
}

function cleanupOptionsFixture(PDO $pdo, string $slug): void
{
    $pdo->prepare('DELETE FROM plugin_entity_data WHERE entity_slug = :slug')->execute([':slug' => $slug]);
    $pdo->prepare('DELETE FROM plugins WHERE slug = :slug')->execute([':slug' => $slug]);
}

echo str_repeat('-', 40) . "\n";

TestSuite::run('label concatenates fields (no summaryView key ⇒ default true) before custom_fields', function () use ($pdo): void {
    $slug = 'test_options_' . bin2hex(random_bytes(3));

    try {
        insertOptionsEntityPlugin(
            $pdo,
            $slug,
            ['name' => ['type' => 'string', 'required' => true, 'label' => 'Name'], 'surnames' => ['type' => 'string', 'required' => true, 'label' => 'Surnames']],
            [['key' => 'dni', 'type' => 'string', 'required' => false, 'label' => 'DNI', 'summaryView' => false]]
        );
        $id = insertOptionsRecord($pdo, $slug, ['name' => 'Juan', 'surnames' => 'Perez', 'dni' => '12345678A']);

        $ctrl = buildOptionsController();
        $result = callOptionsController($ctrl, ['slug' => $slug]);

        assertTrue($result['ok'] ?? false, 'options() must succeed');
        $options = $result['data'] ?? [];
        assertEquals(1, count($options), 'must return exactly one option');
        assertEquals($id, $options[0]['id'] ?? null, 'option id must be the record id');
        assertEquals('Juan Perez', $options[0]['label'] ?? null, 'label must concatenate fields marked as summary, excluding summaryView:false custom_fields');
    } finally {
        cleanupOptionsFixture($pdo, $slug);
    }
});

TestSuite::run('a custom_field with summaryView:true is included after fields', function () use ($pdo): void {
    $slug = 'test_options_' . bin2hex(random_bytes(3));

    try {
        insertOptionsEntityPlugin(
            $pdo,
            $slug,
            ['name' => ['type' => 'string', 'required' => true, 'label' => 'Name']],
            [['key' => 'nickname', 'type' => 'string', 'required' => false, 'label' => 'Nickname', 'summaryView' => true]]
        );
        insertOptionsRecord($pdo, $slug, ['name' => 'Ana', 'nickname' => 'Anita']);

        $ctrl = buildOptionsController();
        $result = callOptionsController($ctrl, ['slug' => $slug]);

        $options = $result['data'] ?? [];
        assertEquals('Ana Anita', $options[0]['label'] ?? null, 'summary custom_field must be appended after fields, in that order');
    } finally {
        cleanupOptionsFixture($pdo, $slug);
    }
});

TestSuite::run('falls back to the record id when no field counts as summary', function () use ($pdo): void {
    $slug = 'test_options_' . bin2hex(random_bytes(3));

    try {
        insertOptionsEntityPlugin(
            $pdo,
            $slug,
            [],
            [['key' => 'note', 'type' => 'string', 'required' => false, 'label' => 'Note', 'summaryView' => false]]
        );
        $id = insertOptionsRecord($pdo, $slug, ['note' => 'hidden']);

        $ctrl = buildOptionsController();
        $result = callOptionsController($ctrl, ['slug' => $slug]);

        $options = $result['data'] ?? [];
        assertEquals($id, $options[0]['label'] ?? null, 'label must fall back to the record id when there is no summary field');
    } finally {
        cleanupOptionsFixture($pdo, $slug);
    }
});

TestSuite::run('a missing value for a summary key is omitted without producing a literal "null"', function () use ($pdo): void {
    $slug = 'test_options_' . bin2hex(random_bytes(3));

    try {
        insertOptionsEntityPlugin(
            $pdo,
            $slug,
            ['name' => ['type' => 'string', 'required' => true, 'label' => 'Name'], 'surnames' => ['type' => 'string', 'required' => false, 'label' => 'Surnames']]
        );
        insertOptionsRecord($pdo, $slug, ['name' => 'Ana']);

        $ctrl = buildOptionsController();
        $result = callOptionsController($ctrl, ['slug' => $slug]);

        $options = $result['data'] ?? [];
        assertEquals('Ana', $options[0]['label'] ?? null, 'a missing summary value must be skipped, not rendered as "null"');
    } finally {
        cleanupOptionsFixture($pdo, $slug);
    }
});

TestSuite::run('returns every active record without pagination', function () use ($pdo): void {
    $slug = 'test_options_' . bin2hex(random_bytes(3));

    try {
        insertOptionsEntityPlugin($pdo, $slug, ['name' => ['type' => 'string', 'required' => true, 'label' => 'Name']]);
        for ($i = 0; $i < 25; $i++) {
            insertOptionsRecord($pdo, $slug, ['name' => "Record {$i}"]);
        }

        $ctrl = buildOptionsController();
        $result = callOptionsController($ctrl, ['slug' => $slug]);

        assertEquals(25, count($result['data'] ?? []), 'must return all 25 records, no page_size cap');
    } finally {
        cleanupOptionsFixture($pdo, $slug);
    }
});

TestSuite::run('GET /entities/{unknown}/options responds 404', function () use ($pdo): void {
    $ctrl = buildOptionsController();
    $result = callOptionsController($ctrl, ['slug' => 'not_a_real_entity_' . bin2hex(random_bytes(3))]);

    assertTrue(!($result['ok'] ?? true), 'options() must fail for an unknown entity slug');
    assertEquals(404, $result['error']['code'] ?? null, 'must respond with 404');
});

echo str_repeat('-', 40) . "\n";
TestSuite::summary();
exit(TestSuite::exitCode());
