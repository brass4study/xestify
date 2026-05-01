<?php

/**
 * GenericRepositoryTest — Integration tests.
 *
 * Tests the full CRUD lifecycle of GenericRepository against a live
 * PostgreSQL database. All test rows are cleaned up within each test
 * (via transactions or explicit DELETE).
 *
 * Run:
 *   php backend/tests/integration/GenericRepositoryTest.php
 */

declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__, 2));

require_once BASE_PATH . '/tests/unit/helpers.php';
require_once BASE_PATH . '/src/Exceptions/DatabaseException.php';
require_once BASE_PATH . '/src/Exceptions/RepositoryException.php';
require_once BASE_PATH . '/src/Core/Database.php';
require_once BASE_PATH . '/src/Repositories/GenericRepository.php';

use Xestify\Core\Database;
use Xestify\Exceptions\DatabaseException;
use Xestify\Exceptions\RepositoryException;
use Xestify\Repositories\GenericRepository;

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
    echo "[SKIP] PostgreSQL not reachable — all GenericRepositoryTest cases skipped.\n";
    echo "       Configure backend/.env with valid DB_* vars and run 002_core.sql.\n";
    echo "----------------------------------------\n";
    echo "Resultado: 0 passed, 0 failed (skipped)\n";
    exit(0);
}

/** @var string Unique entity slug per test run to avoid cross-test contamination. */
const TEST_SLUG = 'test_generic_repo';

// Helper: delete all test rows between test cases.
function cleanTestRows(): void
{
    Database::connection()->exec(
        "DELETE FROM entity_data WHERE entity_slug = '" . TEST_SLUG . "'"
    );
}

// ---------------------------------------------------------------------------
// Tests
// ---------------------------------------------------------------------------

TestSuite::run('create() inserts a record and returns it with id', function (): void {
    cleanTestRows();
    $repo = new GenericRepository(Database::connection());

    $row = $repo->create(TEST_SLUG, ['name' => 'Prueba', 'value' => 42]);

    assertTrue(isset($row['id']) && $row['id'] !== '', 'id must be present');
    assertTrue($row['entity_slug'] === TEST_SLUG, 'entity_slug must match');
    assertTrue($row['deleted_at'] === null, 'deleted_at must be null');

    cleanTestRows();
});

TestSuite::run('find() retrieves an existing record', function (): void {
    cleanTestRows();
    $repo = new GenericRepository(Database::connection());
    $created = $repo->create(TEST_SLUG, ['key' => 'find_test']);

    $found = $repo->find((string) $created['id']);

    assertTrue($found !== null, 'find() must return the record');
    assertTrue($found['id'] === $created['id'], 'ids must match');

    cleanTestRows();
});

TestSuite::run('find() returns null for non-existent id', function (): void {
    $repo = new GenericRepository(Database::connection());

    $result = $repo->find('00000000-0000-0000-0000-000000000000');

    assertTrue($result === null, 'find() must return null for unknown id');
});

TestSuite::run('all() returns only active records for entity_slug', function (): void {
    cleanTestRows();
    $repo = new GenericRepository(Database::connection());
    $repo->create(TEST_SLUG, ['n' => 1]);
    $repo->create(TEST_SLUG, ['n' => 2]);
    $created3 = $repo->create(TEST_SLUG, ['n' => 3]);
    $repo->delete((string) $created3['id']);

    $rows = $repo->all(TEST_SLUG);

    assertTrue(count($rows) === 2, 'all() must return 2 active records, got: ' . count($rows));

    cleanTestRows();
});

TestSuite::run('update() merges content into existing record', function (): void {
    cleanTestRows();
    $repo = new GenericRepository(Database::connection());
    $created = $repo->create(TEST_SLUG, ['a' => 1, 'b' => 2]);

    $updated = $repo->update((string) $created['id'], ['b' => 99, 'c' => 3]);

    $content = json_decode((string) $updated['content'], true);
    assertTrue(is_array($content), 'content must decode as array');
    assertTrue(($content['a'] ?? null) === 1, 'original key a must be preserved');
    assertTrue(($content['b'] ?? null) === 99, 'key b must be updated to 99');
    assertTrue(($content['c'] ?? null) === 3, 'new key c must be added');

    cleanTestRows();
});

TestSuite::run('delete() soft-deletes a record (deleted_at set)', function (): void {
    cleanTestRows();
    $repo = new GenericRepository(Database::connection());
    $created = $repo->create(TEST_SLUG, ['x' => 1]);

    $repo->delete((string) $created['id']);

    $found = $repo->find((string) $created['id']);
    assertTrue($found === null, 'find() must return null after soft delete');

    cleanTestRows();
});

TestSuite::run('restore() un-deletes a soft-deleted record', function (): void {
    cleanTestRows();
    $repo = new GenericRepository(Database::connection());
    $created = $repo->create(TEST_SLUG, ['y' => 7]);
    $repo->delete((string) $created['id']);

    $repo->restore((string) $created['id']);

    $found = $repo->find((string) $created['id']);
    assertTrue($found !== null, 'restore() must make the record visible again');

    cleanTestRows();
});
