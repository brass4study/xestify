<?php

/**
 * EntityMetadataTableTest — Integration tests.
 *
 * Verifies that the entity_metadata table was created correctly by migration
 * 002_core.sql. Requires a live PostgreSQL connection.
 *
 * Run:
 *   php backend/tests/integration/EntityMetadataTableTest.php
 */

declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__, 2));

require_once BASE_PATH . '/tests/unit/helpers.php';
require_once BASE_PATH . '/src/exceptions/DatabaseException.php';
require_once BASE_PATH . '/src/core/Database.php';

use Xestify\core\Database;
use Xestify\exceptions\DatabaseException;

const QUERY_EXECUTE_MSG = 'Query should execute';

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
    echo "[SKIP] PostgreSQL not reachable — all EntityMetadataTableTest cases skipped.\n";
    echo "       Configure backend/.env with valid DB_* vars and run 002_core.sql.\n";
    echo "----------------------------------------\n";
    echo "Resultado: 0 passed, 0 failed (skipped)\n";
    exit(0);
}

// ---------------------------------------------------------------------------
// Tests
// ---------------------------------------------------------------------------

TestSuite::run('entity_metadata table exists after migration', function (): void {
    $pdo  = Database::connection();
    $stmt = $pdo->query(
        "SELECT EXISTS (
            SELECT 1 FROM information_schema.tables
            WHERE table_schema = 'public'
            AND   table_name   = 'entity_metadata'
        ) AS exists"
    );
    assertTrue($stmt !== false, QUERY_EXECUTE_MSG);
    $row = $stmt->fetch();
    assertTrue($row !== false && $row['exists'] === true, 'entity_metadata table must exist');
});

TestSuite::run('entity_metadata has expected columns', function (): void {
    $pdo  = Database::connection();
    $stmt = $pdo->query(
        "SELECT column_name FROM information_schema.columns
         WHERE table_schema = 'public' AND table_name = 'entity_metadata'
         ORDER BY ordinal_position"
    );
    assertTrue($stmt !== false, QUERY_EXECUTE_MSG);
    $columns = array_column($stmt->fetchAll(), 'column_name');
    foreach (['id', 'entity_slug', 'schema_version', 'schema_json', 'created_at'] as $col) {
        assertTrue(in_array($col, $columns, true), "Column '{$col}' must exist");
    }
});

TestSuite::run('entity_metadata has index on (entity_slug, schema_version)', function (): void {
    $pdo  = Database::connection();
    $stmt = $pdo->query(
        "SELECT COUNT(*) AS cnt
         FROM pg_indexes
         WHERE schemaname = 'public'
           AND tablename = 'entity_metadata'
           AND indexname = 'idx_entity_metadata_slug_version'"
    );
    assertTrue($stmt !== false, QUERY_EXECUTE_MSG);
    $row = $stmt->fetch();
    assertTrue((int) ($row['cnt'] ?? 0) >= 1, 'Expected index idx_entity_metadata_slug_version');
});

TestSuite::run('entity_metadata schema_json check constraint rejects missing fields key', function (): void {
    $pdo = Database::connection();

    $inserted = false;
    $failedByConstraint = false;
    $errorMessage = '';
    $pdo->exec('BEGIN');
    try {
        $stmt = $pdo->prepare(
            'INSERT INTO entity_metadata (entity_slug, schema_version, schema_json)
             VALUES (:slug, :version, :schema_json)'
        );
        $stmt->execute([
            ':slug' => 'clientes',
            ':version' => 1,
            ':schema_json' => '{"title":"Schema sin fields"}',
        ]);
        $inserted = true;
    } catch (\PDOException $e) {
        $inserted = false;
        $errorMessage = strtolower($e->getMessage());
        $failedByConstraint = str_contains($errorMessage, 'entity_metadata_schema_json_check')
            || str_contains($errorMessage, 'check constraint');
    }
    $pdo->exec('ROLLBACK');

    assertFalse($inserted, 'Insert should fail when schema_json has no fields key');
    assertTrue($failedByConstraint, 'Insert must fail due to CHECK constraint, not another error');
});

// ---------------------------------------------------------------------------

TestSuite::summary();
exit(TestSuite::exitCode());
