<?php

/**
 * EntityControllerTest — Integration (E2E) tests for EntityController.
 *
 * Calls controller methods directly (no HTTP server needed).
 * Requires a live PostgreSQL connection with 002_plugin_entity_data.sql applied.
 *
 * Run:
 *   php backend/tests/integration/EntityControllerTest.php
 */

declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__, 2));

define('MSG_OK_TRUE',    'ok must be true');
define('MSG_OK_FALSE',   'ok must be false');
define('MSG_CODE_404',   'code must be 404');

require_once BASE_PATH . '/tests/unit/helpers.php';
require_once BASE_PATH . '/tests/helpers/autoload.php';
require_once BASE_PATH . '/src/exceptions/DatabaseException.php';
require_once BASE_PATH . '/src/exceptions/RepositoryException.php';
require_once BASE_PATH . '/src/exceptions/EntityServiceException.php';
require_once BASE_PATH . '/src/exceptions/ValidationException.php';
require_once BASE_PATH . '/src/core/Database.php';
require_once BASE_PATH . '/src/core/Request.php';
require_once BASE_PATH . '/src/core/Response.php';
require_once BASE_PATH . '/src/repositories/GenericRepository.php';
require_once BASE_PATH . '/tests/unit/validation_bootstrap.php';
require_once BASE_PATH . '/src/services/EntityService.php';
require_once BASE_PATH . '/src/exceptions/HookException.php';
require_once BASE_PATH . '/src/core/HookDispatcher.php';
require_once BASE_PATH . '/src/controllers/EntityController.php';

use Xestify\controllers\EntityController;
use Xestify\controllers\ExtensionPluginDataStore;
use Xestify\core\Database;
use Xestify\core\Request;
use Xestify\exceptions\DatabaseException;
use Xestify\core\HookDispatcher;
use Xestify\plugins\discovery\PluginSchemaCodec;
use Xestify\plugins\schema\ReverseRelationTabResolver;
use Xestify\repositories\GenericRepository;
use Xestify\repositories\PluginRepository;
use Xestify\services\EntityService;
use Xestify\services\ValidationService;

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
    Database::connection();
} catch (DatabaseException) {
    echo "[SKIP] PostgreSQL not reachable — all EntityControllerTest cases skipped.\n";
    echo "       Configure backend/.env with valid DB_* vars and run 002_plugin_entity_data.sql.\n";
    echo "----------------------------------------\n";
    echo "Resultado: 0 passed, 0 failed (skipped)\n";
    exit(0);
}

// ---------------------------------------------------------------------------
// Constants and helpers
// ---------------------------------------------------------------------------

const CTRL_ENTITY_SLUG = 'test_entity_ctrl';
const CTRL_ENTITY_VERSION = '1.0.0';
const SQL_DELETE_PLUGIN_BY_SLUG = 'DELETE FROM plugins WHERE slug = :slug';

const CTRL_SCHEMA_JSON = <<<'JSON'
{
  "fields": {
    "title": {"type": "string", "required": true},
    "score": {"type": "number", "required": false}
  }
}
JSON;

const CTRL_ENTITY_SLUG_DESCRIBED = 'test_entity_ctrl_described';

function seedCtrlDescribedSchema(string $description): void
{
    $manifest = json_encode([
        'name' => CTRL_ENTITY_SLUG_DESCRIBED,
        'label' => 'Test Entity Ctrl Described',
        'version' => CTRL_ENTITY_VERSION,
        'type' => 'entity',
        'core_version' => CTRL_ENTITY_VERSION,
        'description' => $description,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    Database::connection()->prepare(
        "INSERT INTO plugins (slug, status, manifest_json, schema_json)
         VALUES (:slug, 'active', CAST(:manifest AS jsonb), CAST(:schema AS jsonb))
         ON CONFLICT (slug) DO UPDATE
         SET status = 'active', manifest_json = EXCLUDED.manifest_json, schema_json = EXCLUDED.schema_json, updated_at = NOW()"
    )->execute([
        ':slug' => CTRL_ENTITY_SLUG_DESCRIBED,
        ':manifest' => $manifest,
        ':schema' => CTRL_SCHEMA_JSON,
    ]);
}

const CTRL_ENTITY_SLUG_IDENTITY = 'test_entity_ctrl_identity';

const CTRL_SCHEMA_WITH_IDENTITY_JSON = <<<'JSON'
{
  "identities": {
    "id": {"type": "uuid", "auto_generated": true, "editable": false},
    "tax_id": {"type": "string", "editable": true}
  },
  "fields": {
    "title": {"type": "string", "required": true}
  }
}
JSON;

function buildController(): EntityController
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

function callController(
    EntityController $ctrl,
    string $method,
    array $params,
    array $body = [],
    array $query = []
): array
{
    $request = new Request($query, $body, [], $params);
    ob_start();
    $ctrl->$method($params, $request);
    $output = ob_get_clean();
    $decoded = json_decode((string) $output, true);
    return is_array($decoded) ? $decoded : [];
}

function seedCtrlSchema(string $slug = CTRL_ENTITY_SLUG, string $schemaJson = CTRL_SCHEMA_JSON): void
{
    $manifest = json_encode([
        'name' => $slug,
        'label' => 'Test Entity Ctrl',
        'version' => CTRL_ENTITY_VERSION,
        'type' => 'entity',
        'core_version' => CTRL_ENTITY_VERSION,
        'description' => '',
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    Database::connection()->prepare(
        "INSERT INTO plugins (slug, status, manifest_json, schema_json)
         VALUES (:slug, 'inactive', CAST(:manifest AS jsonb), CAST(:schema AS jsonb))
         ON CONFLICT (slug) DO UPDATE
         SET manifest_json = EXCLUDED.manifest_json,
             schema_json = EXCLUDED.schema_json,
             updated_at = NOW()"
    )->execute([':slug' => $slug, ':manifest' => $manifest, ':schema' => $schemaJson]);
}

function cleanCtrlData(string $slug = CTRL_ENTITY_SLUG): void
{
    $pdo = Database::connection();
    $pdo->prepare('DELETE FROM plugin_entity_data WHERE entity_slug = :slug')
        ->execute([':slug' => $slug]);
    $pdo->prepare('UPDATE plugins SET schema_json = NULL WHERE slug = :slug')
        ->execute([':slug' => $slug]);
}

function insertCtrlExtensionRow(string $entitySlug, string $recordId, string $pluginSlug = 'test_ext_plugin'): void
{
    Database::connection()->prepare(
        'INSERT INTO plugin_extension_data (plugin_slug, entity_slug, record_id, content)
         VALUES (:plugin, :entity, :record_id, \'{}\'::jsonb)'
    )->execute([':plugin' => $pluginSlug, ':entity' => $entitySlug, ':record_id' => $recordId]);
}

function countCtrlExtensionRows(string $recordId): int
{
    $stmt = Database::connection()->prepare(
        'SELECT COUNT(*) FROM plugin_extension_data WHERE record_id = :record_id'
    );
    $stmt->execute([':record_id' => $recordId]);
    return (int) $stmt->fetchColumn();
}

const CTRL_DEPENDENT_SLUG = 'test_entity_ctrl_dependent';

function seedCtrlDependentPlugin(string $targetEntitySlug): void
{
    $manifest = json_encode([
        'name' => CTRL_DEPENDENT_SLUG,
        'label' => 'Test Entity Ctrl Dependent',
        'version' => CTRL_ENTITY_VERSION,
        'type' => 'entity',
        'core_version' => CTRL_ENTITY_VERSION,
        'description' => '',
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $schema = json_encode([
        'identities' => ['id' => ['type' => 'uuid', 'auto_generated' => true, 'editable' => false]],
        'fields' => [],
        'custom_fields' => [],
        'relations' => [[
            'key' => 'parent_id',
            'label' => 'Parent',
            'type' => 'belongs_to',
            'target_entity' => $targetEntitySlug,
            'target_field' => 'id',
        ]],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    Database::connection()->prepare(
        "INSERT INTO plugins (slug, status, manifest_json, schema_json)
         VALUES (:slug, 'active', CAST(:manifest AS jsonb), CAST(:schema AS jsonb))
         ON CONFLICT (slug) DO UPDATE
         SET status = 'active', manifest_json = EXCLUDED.manifest_json, schema_json = EXCLUDED.schema_json, updated_at = NOW()"
    )->execute([':slug' => CTRL_DEPENDENT_SLUG, ':manifest' => $manifest, ':schema' => $schema]);
}

function insertCtrlDependentRecord(string $parentId): string
{
    $stmt = Database::connection()->prepare(
        "INSERT INTO plugin_entity_data (entity_slug, content)
         VALUES (:slug, CAST(:content AS jsonb)) RETURNING id"
    );
    $stmt->execute([
        ':slug' => CTRL_DEPENDENT_SLUG,
        ':content' => json_encode(['parent_id' => $parentId], JSON_UNESCAPED_UNICODE),
    ]);
    return (string) $stmt->fetchColumn();
}

function cleanCtrlDependentPlugin(): void
{
    $pdo = Database::connection();
    $pdo->prepare('DELETE FROM plugin_entity_data WHERE entity_slug = :slug')
        ->execute([':slug' => CTRL_DEPENDENT_SLUG]);
    $pdo->prepare(SQL_DELETE_PLUGIN_BY_SLUG)
        ->execute([':slug' => CTRL_DEPENDENT_SLUG]);
}

function hasControllerValidationError(array $result, string $field, string $code): bool
{
    $details = $result['error']['details'] ?? [];
    if (!is_array($details)) {
        return false;
    }

    foreach ($details as $error) {
        if (!is_array($error)) {
            continue;
        }

        if (($error['field'] ?? '') === $field && ($error['code'] ?? '') === $code) {
            return is_string($error['message'] ?? null) && $error['message'] !== '';
        }
    }

    return false;
}

// ---------------------------------------------------------------------------
// Tests
// ---------------------------------------------------------------------------

TestSuite::run('GET schema returns schema_json for existing entity', function (): void {
    cleanCtrlData();
    seedCtrlSchema();
    $ctrl = buildController();

    $result = callController($ctrl, 'schema', ['slug' => CTRL_ENTITY_SLUG]);

    assertTrue($result['ok'] ?? false, MSG_OK_TRUE);
    assertTrue(isset($result['data']['schema']), 'data.schema must be present');
    assertTrue($result['data']['entity_slug'] === CTRL_ENTITY_SLUG, 'entity_slug must match');

    cleanCtrlData();
});

TestSuite::run('GET schema returns 404 for unknown entity', function (): void {
    $ctrl = buildController();

    $result = callController($ctrl, 'schema', ['slug' => 'nonexistent_slug_xyz']);

    assertTrue(!($result['ok'] ?? true), MSG_OK_FALSE);
    assertTrue(($result['error']['code'] ?? 0) === 404, MSG_CODE_404);
});

TestSuite::run('GET /entities (listEntities) exposes manifest.description', function (): void {
    seedCtrlDescribedSchema('Entidad base de clientes: nombre, email, telefono y estado activo.');
    $ctrl = buildController();

    $result = callController($ctrl, 'listEntities', []);

    assertTrue($result['ok'] ?? false, MSG_OK_TRUE);
    $entities = is_array($result['data'] ?? null) ? $result['data'] : [];
    $match = null;
    foreach ($entities as $entity) {
        if (($entity['slug'] ?? '') === CTRL_ENTITY_SLUG_DESCRIBED) {
            $match = $entity;
            break;
        }
    }

    assertTrue($match !== null, 'described entity must appear in listEntities()');
    assertEquals(
        'Entidad base de clientes: nombre, email, telefono y estado activo.',
        $match['description'] ?? null,
        'description must travel from manifest_json.description'
    );
});

TestSuite::run('POST create returns 201 and record for valid data', function (): void {
    cleanCtrlData();
    seedCtrlSchema();
    $ctrl = buildController();

    $result = callController($ctrl, 'create', ['slug' => CTRL_ENTITY_SLUG], ['title' => 'Hello']);

    assertTrue($result['ok'] ?? false, MSG_OK_TRUE);
    assertTrue(isset($result['data']['id']), 'data.id must be present');

    cleanCtrlData();
});

TestSuite::run('POST create returns 422 for invalid data', function (): void {
    cleanCtrlData();
    seedCtrlSchema();
    $ctrl = buildController();

    $result = callController($ctrl, 'create', ['slug' => CTRL_ENTITY_SLUG], ['score' => 10]);

    assertTrue(!($result['ok'] ?? true), MSG_OK_FALSE);
    assertTrue(($result['error']['code'] ?? 0) === 422, 'code must be 422');
    assertTrue(
        hasControllerValidationError($result, 'title', 'required'),
        'details must include structured missing title error'
    );

    cleanCtrlData();
});

TestSuite::run('POST create returns a controlled error instead of an uncaught RepositoryException', function (): void {
    cleanCtrlData();
    seedCtrlSchema();
    $ctrl = buildController();

    // Invalid UTF-8 passes StringFieldValidator (only checks is_string) but makes
    // json_encode() fail inside GenericRepository::encodeJson(), which throws
    // RepositoryException — previously uncaught by EntityController::create().
    $result = callController($ctrl, 'create', ['slug' => CTRL_ENTITY_SLUG], ['title' => "Bad\xB1\x31"]);

    assertTrue(!($result['ok'] ?? true), MSG_OK_FALSE);
    assertTrue(is_int($result['error']['code'] ?? null), 'response must be a controlled JSON error, not an uncaught exception');

    cleanCtrlData();
});

TestSuite::run('GET index returns list of active records', function (): void {
    cleanCtrlData();
    seedCtrlSchema();
    $ctrl = buildController();
    callController($ctrl, 'create', ['slug' => CTRL_ENTITY_SLUG], ['title' => 'R1']);
    callController($ctrl, 'create', ['slug' => CTRL_ENTITY_SLUG], ['title' => 'R2']);

    $result = callController($ctrl, 'index', ['slug' => CTRL_ENTITY_SLUG]);

    assertTrue($result['ok'] ?? false, MSG_OK_TRUE);
    assertTrue(count($result['data'] ?? []) === 2, 'must list 2 records');
    assertTrue(($result['meta']['page_size'] ?? 0) === 20, 'default page size must be 20');
    assertTrue(($result['meta']['total'] ?? 0) === 2, 'meta.total must include all active records');

    cleanCtrlData();
});

TestSuite::run('GET index paginates and sorts records without loading all rows', function (): void {
    cleanCtrlData();
    seedCtrlSchema();
    $ctrl = buildController();
    foreach (['Delta', 'Alfa', 'Charlie', 'Beta'] as $title) {
        callController($ctrl, 'create', ['slug' => CTRL_ENTITY_SLUG], ['title' => $title]);
    }

    $result = callController(
        $ctrl,
        'index',
        ['slug' => CTRL_ENTITY_SLUG],
        [],
        ['page' => '2', 'page_size' => '2', 'sort' => 'title', 'direction' => 'asc']
    );

    assertTrue(count($result['data'] ?? []) === 2, 'must return only requested page rows');
    assertTrue(($result['meta']['total'] ?? 0) === 4, 'meta.total must include all matching rows');
    assertTrue(($result['meta']['total_pages'] ?? 0) === 2, 'must report total pages');
    assertTrue(($result['meta']['page'] ?? 0) === 2, 'must report current page');
    $firstContent = json_decode((string) ($result['data'][0]['content'] ?? ''), true);
    assertTrue(($firstContent['title'] ?? '') === 'Charlie', 'second page must preserve requested sorting');

    $largePage = callController(
        $ctrl,
        'index',
        ['slug' => CTRL_ENTITY_SLUG],
        [],
        ['page_size' => '200']
    );
    assertTrue(($largePage['meta']['page_size'] ?? 0) === 200, 'must allow 200 records per page');

    cleanCtrlData();
});

TestSuite::run('GET index accepts an editable identity field as sort criterion', function (): void {
    cleanCtrlData(CTRL_ENTITY_SLUG_IDENTITY);
    seedCtrlSchema(CTRL_ENTITY_SLUG_IDENTITY, CTRL_SCHEMA_WITH_IDENTITY_JSON);
    $ctrl = buildController();

    foreach ([['title' => 'B', 'tax_id' => 'B-002'], ['title' => 'A', 'tax_id' => 'A-001']] as $data) {
        callController($ctrl, 'create', ['slug' => CTRL_ENTITY_SLUG_IDENTITY], $data);
    }

    $result = callController(
        $ctrl,
        'index',
        ['slug' => CTRL_ENTITY_SLUG_IDENTITY],
        [],
        ['sort' => 'tax_id', 'direction' => 'asc']
    );

    assertTrue($result['meta']['sort'] === 'tax_id', 'tax_id (editable identity) must be accepted as sort field, not silently fall back to created_at');
    $firstContent = json_decode((string) ($result['data'][0]['content'] ?? ''), true);
    assertTrue(($firstContent['tax_id'] ?? '') === 'A-001', 'records must come back ordered by the editable identity field');

    cleanCtrlData(CTRL_ENTITY_SLUG_IDENTITY);
});

TestSuite::run('GET show returns single record by id', function (): void {
    cleanCtrlData();
    seedCtrlSchema();
    $ctrl = buildController();
    $created = callController($ctrl, 'create', ['slug' => CTRL_ENTITY_SLUG], ['title' => 'ShowMe']);
    $id = (string) ($created['data']['id'] ?? '');

    $result = callController($ctrl, 'show', ['slug' => CTRL_ENTITY_SLUG, 'id' => $id]);

    assertTrue($result['ok'] ?? false, MSG_OK_TRUE);
    assertTrue(($result['data']['id'] ?? '') === $id, 'id must match');

    cleanCtrlData();
});

TestSuite::run('GET show returns 404 for unknown id', function (): void {
    $ctrl = buildController();

    $result = callController($ctrl, 'show', [
        'slug' => CTRL_ENTITY_SLUG,
        'id' => '00000000-0000-0000-0000-000000000000',
    ]);

    assertTrue(!($result['ok'] ?? true), MSG_OK_FALSE);
    assertTrue(($result['error']['code'] ?? 0) === 404, MSG_CODE_404);
});

TestSuite::run('PUT update merges data into existing record', function (): void {
    cleanCtrlData();
    seedCtrlSchema();
    $ctrl = buildController();
    $created = callController($ctrl, 'create', ['slug' => CTRL_ENTITY_SLUG], ['title' => 'Original']);
    $id = (string) ($created['data']['id'] ?? '');

    $result = callController($ctrl, 'update', ['slug' => CTRL_ENTITY_SLUG, 'id' => $id], ['score' => 99]);

    assertTrue($result['ok'] ?? false, MSG_OK_TRUE);
    assertTrue(($result['data']['id'] ?? '') === $id, 'id must match');

    cleanCtrlData();
});

TestSuite::run('DELETE destroy soft-deletes record — show returns 404 afterwards', function (): void {
    cleanCtrlData();
    seedCtrlSchema();
    $ctrl = buildController();
    $created = callController($ctrl, 'create', ['slug' => CTRL_ENTITY_SLUG], ['title' => 'ToDelete']);
    $id = (string) ($created['data']['id'] ?? '');

    $deleteResult = callController($ctrl, 'destroy', ['slug' => CTRL_ENTITY_SLUG, 'id' => $id]);
    $showResult   = callController($ctrl, 'show', ['slug' => CTRL_ENTITY_SLUG, 'id' => $id]);

    assertTrue($deleteResult['ok'] ?? false, 'delete must return ok');
    assertTrue(!($showResult['ok'] ?? true), 'show must return not found after delete');

    cleanCtrlData();
});

TestSuite::run('DELETE destroy cascades to plugin_extension_data for that record only', function (): void {
    cleanCtrlData();
    seedCtrlSchema();
    $ctrl = buildController();
    $created = callController($ctrl, 'create', ['slug' => CTRL_ENTITY_SLUG], ['title' => 'HasComments']);
    $keeper  = callController($ctrl, 'create', ['slug' => CTRL_ENTITY_SLUG], ['title' => 'Untouched']);
    $id = (string) ($created['data']['id'] ?? '');
    $keeperId = (string) ($keeper['data']['id'] ?? '');

    insertCtrlExtensionRow(CTRL_ENTITY_SLUG, $id);
    insertCtrlExtensionRow(CTRL_ENTITY_SLUG, $id);
    insertCtrlExtensionRow(CTRL_ENTITY_SLUG, $keeperId);

    $deleteResult = callController($ctrl, 'destroy', ['slug' => CTRL_ENTITY_SLUG, 'id' => $id]);

    assertTrue($deleteResult['ok'] ?? false, 'delete must return ok');
    assertTrue(countCtrlExtensionRows($id) === 0, 'extension_data rows for the deleted record must be gone');
    assertTrue(countCtrlExtensionRows($keeperId) === 1, 'extension_data rows for other records must survive');

    cleanCtrlData();
});

TestSuite::run('DELETE destroy returns 422 when another entity has a dependent record', function (): void {
    cleanCtrlData();
    seedCtrlSchema();
    cleanCtrlDependentPlugin();
    seedCtrlDependentPlugin(CTRL_ENTITY_SLUG);
    $ctrl = buildController();
    $created = callController($ctrl, 'create', ['slug' => CTRL_ENTITY_SLUG], ['title' => 'HasDependent']);
    $id = (string) ($created['data']['id'] ?? '');
    insertCtrlDependentRecord($id);

    $deleteResult = callController($ctrl, 'destroy', ['slug' => CTRL_ENTITY_SLUG, 'id' => $id]);
    $showResult = callController($ctrl, 'show', ['slug' => CTRL_ENTITY_SLUG, 'id' => $id]);

    assertTrue(!($deleteResult['ok'] ?? true), MSG_OK_FALSE);
    assertTrue(($deleteResult['error']['code'] ?? 0) === 422, 'code must be 422 when a dependent record blocks deletion');
    assertTrue($showResult['ok'] ?? false, 'record must still exist after the blocked delete');

    cleanCtrlDependentPlugin();
    cleanCtrlData();
});

// ---------------------------------------------------------------------------
// Final cleanup — cleanCtrlData() only clears plugin_entity_data / nulls
// schema_json between tests; the registry rows themselves must be removed
// once, here, or they linger in the plugins listing forever.
// ---------------------------------------------------------------------------

$finalPdo = Database::connection();
$finalPdo->prepare(SQL_DELETE_PLUGIN_BY_SLUG)->execute([':slug' => CTRL_ENTITY_SLUG]);
$finalPdo->prepare(SQL_DELETE_PLUGIN_BY_SLUG)->execute([':slug' => CTRL_ENTITY_SLUG_IDENTITY]);
$finalPdo->prepare(SQL_DELETE_PLUGIN_BY_SLUG)->execute([':slug' => CTRL_ENTITY_SLUG_DESCRIBED]);

// ---------------------------------------------------------------------------
// Summary
// ---------------------------------------------------------------------------

TestSuite::summary();
exit(TestSuite::exitCode());

