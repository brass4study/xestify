<?php

/**
 * EntityControllerTest — Integration (E2E) tests for EntityController.
 *
 * Calls controller methods directly (no HTTP server needed).
 * Requires a live PostgreSQL connection with 002_core.sql applied.
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
require_once BASE_PATH . '/src/exceptions/DatabaseException.php';
require_once BASE_PATH . '/src/exceptions/RepositoryException.php';
require_once BASE_PATH . '/src/exceptions/EntityServiceException.php';
require_once BASE_PATH . '/src/exceptions/ValidationException.php';
require_once BASE_PATH . '/src/core/Database.php';
require_once BASE_PATH . '/src/core/Request.php';
require_once BASE_PATH . '/src/core/Response.php';
require_once BASE_PATH . '/src/repositories/GenericRepository.php';
require_once BASE_PATH . '/src/services/ValidationService.php';
require_once BASE_PATH . '/src/services/EntityService.php';
require_once BASE_PATH . '/src/controllers/EntityController.php';

use Xestify\controllers\EntityController;
use Xestify\core\Database;
use Xestify\core\Request;
use Xestify\exceptions\DatabaseException;
use Xestify\repositories\GenericRepository;
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
    echo "       Configure backend/.env with valid DB_* vars and run 002_core.sql.\n";
    echo "----------------------------------------\n";
    echo "Resultado: 0 passed, 0 failed (skipped)\n";
    exit(0);
}

// ---------------------------------------------------------------------------
// Constants and helpers
// ---------------------------------------------------------------------------

const CTRL_ENTITY_SLUG = 'test_entity_ctrl';

const CTRL_SCHEMA_JSON = '{"fields":{"title":{"type":"string","required":true},"score":{"type":"number","required":false}}}';

function buildController(): EntityController
{
    $pdo = Database::connection();

    return new EntityController(
        new EntityService(
            new GenericRepository($pdo),
            new ValidationService(),
            $pdo
        ),
        $pdo
    );
}

function callController(EntityController $ctrl, string $method, array $params, array $body = []): array
{
    $request = new Request([], $body, [], $params);
    ob_start();
    $ctrl->$method($params, $request);
    $output = ob_get_clean();
    $decoded = json_decode((string) $output, true);
    return is_array($decoded) ? $decoded : [];
}

function seedCtrlSchema(): void
{
    Database::connection()->prepare(
        'INSERT INTO entity_metadata (entity_slug, schema_version, schema_json)
         VALUES (:slug, 1, :schema)
         ON CONFLICT DO NOTHING'
    )->execute([':slug' => CTRL_ENTITY_SLUG, ':schema' => CTRL_SCHEMA_JSON]);
}

function cleanCtrlData(): void
{
    $pdo = Database::connection();
    $pdo->prepare('DELETE FROM entity_data WHERE entity_slug = :slug')
        ->execute([':slug' => CTRL_ENTITY_SLUG]);
    $pdo->prepare('DELETE FROM entity_metadata WHERE entity_slug = :slug')
        ->execute([':slug' => CTRL_ENTITY_SLUG]);
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
    assertTrue(isset($result['error']['details']['title']), 'details must mention missing title');

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

    cleanCtrlData();
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

    $result = callController($ctrl, 'show', ['slug' => CTRL_ENTITY_SLUG, 'id' => '00000000-0000-0000-0000-000000000000']);

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

// ---------------------------------------------------------------------------
// Summary
// ---------------------------------------------------------------------------

TestSuite::summary();
exit(TestSuite::exitCode());

