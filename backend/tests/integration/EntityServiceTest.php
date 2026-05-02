<?php

/**
 * EntityServiceTest — Integration tests for EntityService.
 *
 * Exercises create/update/delete/get/list against a live PostgreSQL database.
 * A test schema is seeded into plugin_entity_metadata before each test and cleaned up
 * afterwards. All plugin_entity_data rows for the test slug are also removed.
 *
 * Run:
 *   php backend/tests/integration/EntityServiceTest.php
 */

declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__, 2));

require_once BASE_PATH . '/tests/unit/helpers.php';
require_once BASE_PATH . '/src/exceptions/DatabaseException.php';
require_once BASE_PATH . '/src/exceptions/RepositoryException.php';
require_once BASE_PATH . '/src/exceptions/EntityServiceException.php';
require_once BASE_PATH . '/src/exceptions/ValidationException.php';
require_once BASE_PATH . '/src/core/Database.php';
require_once BASE_PATH . '/src/repositories/GenericRepository.php';
require_once BASE_PATH . '/src/services/ValidationService.php';
require_once BASE_PATH . '/src/services/EntityService.php';

use Xestify\core\Database;
use Xestify\exceptions\DatabaseException;
use Xestify\exceptions\EntityServiceException;
use Xestify\exceptions\ValidationException;
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
    echo "[SKIP] PostgreSQL not reachable — all EntityServiceTest cases skipped.\n";
    echo "       Configure backend/.env with valid DB_* vars and run 002_core.sql.\n";
    echo "----------------------------------------\n";
    echo "Resultado: 0 passed, 0 failed (skipped)\n";
    exit(0);
}

// ---------------------------------------------------------------------------
// Constants and helpers
// ---------------------------------------------------------------------------

const TEST_ENTITY_SLUG = 'test_entity_service';

const TEST_SCHEMA_JSON = '{"fields":{"name":{"type":"string","required":true},"age":{"type":"number","required":false}}}';

function buildService(): EntityService
{
    $pdo = Database::connection();

    return new EntityService(
        new GenericRepository($pdo),
        new ValidationService(),
        $pdo
    );
}

function seedSchema(): void
{
    Database::connection()->prepare(
        "INSERT INTO plugins (slug, plugin_type, version, status, schema_version, schema_json)
         VALUES (:slug, 'entity', '1.0.0', 'inactive', 1, :schema)
         ON CONFLICT (slug) DO UPDATE
         SET schema_json = EXCLUDED.schema_json,
             schema_version = EXCLUDED.schema_version,
             updated_at = NOW()"
    )->execute([':slug' => TEST_ENTITY_SLUG, ':schema' => TEST_SCHEMA_JSON]);
}

function cleanTestData(): void
{
    $pdo = Database::connection();
    $pdo->prepare('DELETE FROM plugin_entity_data WHERE entity_slug = :slug')
        ->execute([':slug' => TEST_ENTITY_SLUG]);
    $pdo->prepare('UPDATE plugins SET schema_json = NULL WHERE slug = :slug')
        ->execute([':slug' => TEST_ENTITY_SLUG]);
}

// ---------------------------------------------------------------------------
// Tests
// ---------------------------------------------------------------------------

TestSuite::run('createRecord() persists valid data and returns row with id', function (): void {
    cleanTestData();
    seedSchema();
    $svc = buildService();

    $record = $svc->createRecord(TEST_ENTITY_SLUG, ['name' => 'Alice', 'age' => 30]);

    assertTrue(isset($record['id']) && $record['id'] !== '', 'record must have an id');
    assertTrue($record['entity_slug'] === TEST_ENTITY_SLUG, 'entity_slug must match');
    assertTrue($record['deleted_at'] === null, 'deleted_at must be null');

    cleanTestData();
});

TestSuite::run('createRecord() throws ValidationException for missing required field', function (): void {
    cleanTestData();
    seedSchema();
    $svc = buildService();
    $caught = false;
    $errors = [];

    try {
        $svc->createRecord(TEST_ENTITY_SLUG, ['age' => 25]);
    } catch (ValidationException $e) {
        $caught = true;
        $errors = $e->getErrors();
    }

    assertTrue($caught, 'ValidationException must be thrown');
    assertTrue(isset($errors['name']), 'errors must contain the missing required field');

    cleanTestData();
});

TestSuite::run('createRecord() throws EntityServiceException when schema is missing', function (): void {
    cleanTestData();
    $svc = buildService();
    $caught = false;

    try {
        $svc->createRecord('nonexistent_slug_xyz', ['name' => 'Test']);
    } catch (EntityServiceException $e) {
        $caught = true;
    }

    assertTrue($caught, 'EntityServiceException must be thrown when no schema exists');
});

TestSuite::run('updateRecord() merges data without requiring all fields', function (): void {
    cleanTestData();
    seedSchema();
    $svc = buildService();

    $created = $svc->createRecord(TEST_ENTITY_SLUG, ['name' => 'Bob', 'age' => 20]);
    $updated = $svc->updateRecord((string) $created['id'], TEST_ENTITY_SLUG, ['age' => 21]);

    assertTrue($updated['id'] === $created['id'], 'id must remain the same');

    cleanTestData();
});

TestSuite::run('deleteRecord() soft deletes — getRecord() returns null afterwards', function (): void {
    cleanTestData();
    seedSchema();
    $svc = buildService();

    $record = $svc->createRecord(TEST_ENTITY_SLUG, ['name' => 'Charlie']);
    $svc->deleteRecord((string) $record['id']);

    $found = $svc->getRecord((string) $record['id']);

    assertTrue($found === null, 'getRecord() must return null after delete');

    cleanTestData();
});

TestSuite::run('listRecords() returns only active records', function (): void {
    cleanTestData();
    seedSchema();
    $svc = buildService();

    $svc->createRecord(TEST_ENTITY_SLUG, ['name' => 'D1']);
    $svc->createRecord(TEST_ENTITY_SLUG, ['name' => 'D2']);
    $r3 = $svc->createRecord(TEST_ENTITY_SLUG, ['name' => 'D3']);
    $svc->deleteRecord((string) $r3['id']);

    $rows = $svc->listRecords(TEST_ENTITY_SLUG);

    assertTrue(count($rows) === 2, 'listRecords() must return 2 active records, got: ' . count($rows));

    cleanTestData();
});

// ---------------------------------------------------------------------------
// Summary
// ---------------------------------------------------------------------------

TestSuite::summary();
exit(TestSuite::exitCode());

