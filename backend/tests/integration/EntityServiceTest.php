<?php

/**
 * EntityServiceTest — Integration tests for EntityService.
 *
 * Exercises create/update/delete/get/list against a live PostgreSQL database.
 * A test schema is seeded into plugins.schema_json before each test and cleaned up
 * afterwards. All plugin_entity_data rows for the test slug are also removed.
 *
 * Run:
 *   php backend/tests/integration/EntityServiceTest.php
 */

declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__, 2));

require_once BASE_PATH . '/tests/unit/helpers.php';
require_once BASE_PATH . '/tests/helpers/autoload.php';
require_once BASE_PATH . '/src/exceptions/DatabaseException.php';
require_once BASE_PATH . '/src/exceptions/RepositoryException.php';
require_once BASE_PATH . '/src/exceptions/EntityServiceException.php';
require_once BASE_PATH . '/src/exceptions/HookException.php';
require_once BASE_PATH . '/src/exceptions/ValidationException.php';
require_once BASE_PATH . '/src/core/Database.php';
require_once BASE_PATH . '/src/repositories/GenericRepository.php';
require_once BASE_PATH . '/tests/unit/validation_bootstrap.php';
require_once BASE_PATH . '/src/core/HookDispatcher.php';
require_once BASE_PATH . '/src/services/EntityService.php';

use Xestify\controllers\ExtensionPluginDataStore;
use Xestify\core\Database;
use Xestify\exceptions\DatabaseException;
use Xestify\exceptions\EntityServiceException;
use Xestify\exceptions\HookException;
use Xestify\exceptions\ValidationException;
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
    echo "[SKIP] PostgreSQL not reachable — all EntityServiceTest cases skipped.\n";
    echo "       Configure backend/.env with valid DB_* vars and run 002_plugin_entity_data.sql.\n";
    echo "----------------------------------------\n";
    echo "Resultado: 0 passed, 0 failed (skipped)\n";
    exit(0);
}

// ---------------------------------------------------------------------------
// Constants and helpers
// ---------------------------------------------------------------------------

const TEST_ENTITY_SLUG = 'test_entity_service';
const DELETE_BY_SLUG_SQL = 'DELETE FROM plugin_entity_data WHERE entity_slug = :slug';
const DELETE_PLUGIN_BY_SLUG_SQL = 'DELETE FROM plugins WHERE slug = :slug';
const TEST_MANIFEST_VERSION = '1.0.0';

define('TEST_SCHEMA_JSON', <<<'JSON'
{
  "fields": {
    "name": {"type": "string", "required": true},
    "age": {"type": "number", "required": false}
  },
  "custom_fields": [
    {"key": "phone", "type": "string", "required": false},
    {"key": "creation_stamp", "type": "timestamp", "required": false},
    {"key": "is_active", "type": "boolean", "required": false}
  ]
}
JSON);

function buildService(): EntityService
{
    $pdo = Database::connection();

    return new EntityService(
        new GenericRepository($pdo),
        new ValidationService(),
        $pdo,
        new ExtensionPluginDataStore($pdo),
        new ReverseRelationTabResolver(new PluginRepository($pdo, new PluginSchemaCodec()))
    );
}

function seedSchema(): void
{
    $manifest = json_encode([
        'name' => TEST_ENTITY_SLUG,
        'label' => TEST_ENTITY_SLUG,
        'version' => '1.0.0',
        'type' => 'entity',
        'core_version' => '1.0.0',
        'description' => '',
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    Database::connection()->prepare(
        "INSERT INTO plugins (slug, status, manifest_json, schema_json)
         VALUES (:slug, 'inactive', CAST(:manifest AS jsonb), CAST(:schema AS jsonb))
         ON CONFLICT (slug) DO UPDATE
         SET schema_json = EXCLUDED.schema_json,
             updated_at = NOW()"
    )->execute([':slug' => TEST_ENTITY_SLUG, ':manifest' => $manifest, ':schema' => TEST_SCHEMA_JSON]);
}

function cleanTestData(): void
{
    $pdo = Database::connection();
    $pdo->prepare(DELETE_BY_SLUG_SQL)->execute([':slug' => TEST_ENTITY_SLUG]);
    $pdo->prepare('UPDATE plugins SET schema_json = NULL WHERE slug = :slug')
        ->execute([':slug' => TEST_ENTITY_SLUG]);
}

/**
 * @param list<array{field: string, code: string, message: string}> $errors
 */
function hasServiceValidationError(array $errors, string $field, string $code): bool
{
    foreach ($errors as $error) {
        if (($error['field'] ?? '') === $field && ($error['code'] ?? '') === $code) {
            return true;
        }
    }

    return false;
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

TestSuite::run('createRecord() persists custom_fields from schema', function (): void {
    cleanTestData();
    seedSchema();
    $svc = buildService();

    $record = $svc->createRecord(TEST_ENTITY_SLUG, [
        'name' => 'Custom Fields Client',
        'phone' => '+34 600 000 000',
        'creation_stamp' => 'now',
        'is_active' => true,
    ]);

    $content = is_array($record['content'] ?? null)
        ? $record['content']
        : json_decode((string) ($record['content'] ?? '{}'), true);

    assertEquals('+34 600 000 000', $content['phone'] ?? null, 'phone custom field must persist');
    assertEquals('now', $content['creation_stamp'] ?? null, 'creation_stamp custom field must persist');
    assertTrue(($content['is_active'] ?? null) === true, 'is_active custom field must persist');

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
    assertTrue(
        hasServiceValidationError($errors, 'name', 'required'),
        'errors must contain the missing required field'
    );

    cleanTestData();
});

TestSuite::run('updateRecord() rejects invalid payload with structured errors', function (): void {
    cleanTestData();
    seedSchema();
    $svc = buildService();
    $created = $svc->createRecord(TEST_ENTITY_SLUG, ['name' => 'Invalid Update']);
    $caught = false;
    $errors = [];

    try {
        $svc->updateRecord((string) $created['id'], TEST_ENTITY_SLUG, ['is_active' => 'yes']);
    } catch (ValidationException $e) {
        $caught = true;
        $errors = $e->getErrors();
    }

    assertTrue($caught, 'ValidationException must be thrown');
    assertTrue(
        hasServiceValidationError($errors, 'is_active', 'invalid_type'),
        'errors must contain invalid_type for is_active'
    );

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

TestSuite::run('deleteRecord() cascades to plugin_extension_data for that record only', function (): void {
    cleanTestData();
    seedSchema();
    $pdo = Database::connection();
    $svc = buildService();

    $record = $svc->createRecord(TEST_ENTITY_SLUG, ['name' => 'HasExtensionData']);
    $keeper = $svc->createRecord(TEST_ENTITY_SLUG, ['name' => 'Untouched']);
    $recordId = (string) $record['id'];
    $keeperId = (string) $keeper['id'];

    $insertExtension = function (string $recordIdToLink) use ($pdo): void {
        $pdo->prepare(
            'INSERT INTO plugin_extension_data (plugin_slug, entity_slug, record_id, content)
             VALUES (:plugin, :entity, :record_id, \'{}\'::jsonb)'
        )->execute([
            ':plugin' => 'test_ext_plugin',
            ':entity' => TEST_ENTITY_SLUG,
            ':record_id' => $recordIdToLink,
        ]);
    };
    $insertExtension($recordId);
    $insertExtension($recordId);
    $insertExtension($keeperId);

    $svc->deleteRecord($recordId);

    $countFor = function (string $recordIdToCount) use ($pdo): int {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM plugin_extension_data WHERE record_id = :record_id');
        $stmt->execute([':record_id' => $recordIdToCount]);
        return (int) $stmt->fetchColumn();
    };

    assertTrue($countFor($recordId) === 0, 'extension_data rows for the deleted record must be gone');
    assertTrue($countFor($keeperId) === 1, 'extension_data rows for other records must survive');

    $pdo->prepare('DELETE FROM plugin_extension_data WHERE entity_slug = :slug')
        ->execute([':slug' => TEST_ENTITY_SLUG]);
    cleanTestData();
});

TestSuite::run('deleteRecord() throws HookException and keeps the record when another entity depends on it', function (): void {
    cleanTestData();
    seedSchema();
    $pdo = Database::connection();
    $dependentSlug = TEST_ENTITY_SLUG . '_dependent';
    $pdo->prepare(DELETE_BY_SLUG_SQL)->execute([':slug' => $dependentSlug]);
    $pdo->prepare(DELETE_PLUGIN_BY_SLUG_SQL)->execute([':slug' => $dependentSlug]);

    $manifest = json_encode([
        'name' => $dependentSlug, 'label' => $dependentSlug, 'version' => TEST_MANIFEST_VERSION,
        'type' => 'entity', 'core_version' => TEST_MANIFEST_VERSION, 'description' => '',
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $schema = json_encode([
        'identities' => ['id' => ['type' => 'uuid', 'auto_generated' => true, 'editable' => false]],
        'fields' => [],
        'custom_fields' => [],
        'relations' => [[
            'key' => 'parent_id', 'label' => 'Parent', 'type' => 'belongs_to',
            'target_entity' => TEST_ENTITY_SLUG, 'target_field' => 'id',
        ]],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $pdo->prepare(
        "INSERT INTO plugins (slug, status, manifest_json, schema_json)
         VALUES (:slug, 'active', CAST(:manifest AS jsonb), CAST(:schema AS jsonb))"
    )->execute([':slug' => $dependentSlug, ':manifest' => $manifest, ':schema' => $schema]);

    $svc = buildService();
    $parent = $svc->createRecord(TEST_ENTITY_SLUG, ['name' => 'HasDependent']);
    $parentId = (string) $parent['id'];
    $svc->createRecord($dependentSlug, ['parent_id' => $parentId]);

    $caught = false;
    try {
        $svc->deleteRecord($parentId);
    } catch (HookException) {
        $caught = true;
    }

    assertTrue($caught, 'HookException must be thrown when a dependent record blocks deletion');
    assertTrue($svc->getRecord($parentId) !== null, 'parent record must still exist after the blocked delete');

    $pdo->prepare(DELETE_BY_SLUG_SQL)->execute([':slug' => $dependentSlug]);
    $pdo->prepare(DELETE_PLUGIN_BY_SLUG_SQL)->execute([':slug' => $dependentSlug]);
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

TestSuite::run('createRecord() rolls back beforeSave hook writes when persist fails', function (): void {
    cleanTestData();
    seedSchema();
    $pdo = Database::connection();
    $markerSlug = TEST_ENTITY_SLUG . '_hook_marker';

    $hooks = new HookDispatcher();
    $hooks->register('beforeSave', function (array $context) use ($pdo, $markerSlug): array {
        $pdo->prepare(
            "INSERT INTO plugin_entity_data (entity_slug, content) VALUES (:slug, '{}'::jsonb)"
        )->execute([':slug' => $markerSlug]);

        return $context;
    });

    $svc = new EntityService(
        new GenericRepository($pdo),
        new ValidationService(),
        $pdo,
        new ExtensionPluginDataStore($pdo),
        new ReverseRelationTabResolver(new PluginRepository($pdo, new PluginSchemaCodec())),
        $hooks
    );
    $caught = false;

    try {
        try {
            // NAN passes NumberFieldValidator (it is a float) but json_encode() cannot
            // encode it, so GenericRepository::create() throws RepositoryException
            // *after* the beforeSave hook has already written its own row.
            $svc->createRecord(TEST_ENTITY_SLUG, ['name' => 'Broken', 'age' => NAN]);
        } catch (\Throwable) {
            $caught = true;
        }

        $stmt = $pdo->prepare('SELECT COUNT(*) FROM plugin_entity_data WHERE entity_slug = :slug');
        $stmt->execute([':slug' => $markerSlug]);
        $markerCount = (int) $stmt->fetchColumn();

        assertTrue($caught, 'createRecord() must fail because NAN cannot be encoded as JSON');
        assertTrue(
            $markerCount === 0,
            'beforeSave hook write must be rolled back when the persist step fails, got: ' . $markerCount
        );
    } finally {
        cleanTestData();
        $pdo->prepare(DELETE_BY_SLUG_SQL)->execute([':slug' => $markerSlug]);
    }
});

TestSuite::run('updateRecord() rolls back beforeSave hook writes when persist fails', function (): void {
    cleanTestData();
    seedSchema();
    $pdo = Database::connection();
    $markerSlug = TEST_ENTITY_SLUG . '_hook_marker';

    $plainSvc = buildService();
    $created = $plainSvc->createRecord(TEST_ENTITY_SLUG, ['name' => 'Original']);

    $hooks = new HookDispatcher();
    $hooks->register('beforeSave', function (array $context) use ($pdo, $markerSlug): array {
        $pdo->prepare(
            "INSERT INTO plugin_entity_data (entity_slug, content) VALUES (:slug, '{}'::jsonb)"
        )->execute([':slug' => $markerSlug]);

        return $context;
    });

    $svc = new EntityService(
        new GenericRepository($pdo),
        new ValidationService(),
        $pdo,
        new ExtensionPluginDataStore($pdo),
        new ReverseRelationTabResolver(new PluginRepository($pdo, new PluginSchemaCodec())),
        $hooks
    );
    $caught = false;

    try {
        try {
            $svc->updateRecord((string) $created['id'], TEST_ENTITY_SLUG, ['age' => NAN]);
        } catch (\Throwable) {
            $caught = true;
        }

        $stmt = $pdo->prepare('SELECT COUNT(*) FROM plugin_entity_data WHERE entity_slug = :slug');
        $stmt->execute([':slug' => $markerSlug]);
        $markerCount = (int) $stmt->fetchColumn();

        assertTrue($caught, 'updateRecord() must fail because NAN cannot be encoded as JSON');
        assertTrue(
            $markerCount === 0,
            'beforeSave hook write must be rolled back when the persist step fails, got: ' . $markerCount
        );
    } finally {
        cleanTestData();
        $pdo->prepare(DELETE_BY_SLUG_SQL)->execute([':slug' => $markerSlug]);
    }
});

// ---------------------------------------------------------------------------
// Final cleanup — cleanTestData() only clears plugin_entity_data / nulls
// schema_json between tests; the registry row itself must be removed once,
// here, or it lingers in the plugins listing forever.
// ---------------------------------------------------------------------------

Database::connection()->prepare('DELETE FROM plugins WHERE slug = :slug')->execute([':slug' => TEST_ENTITY_SLUG]);

// ---------------------------------------------------------------------------
// Summary
// ---------------------------------------------------------------------------

TestSuite::summary();
exit(TestSuite::exitCode());

