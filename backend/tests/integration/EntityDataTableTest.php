<?php

/**
 * EntityDataTableTest — Integration tests.
 *
 * Verifies that the entity_data table was created correctly by migration
 * 002_core.sql. Requires a live PostgreSQL connection.
 *
 * Run:
 *   php backend/tests/integration/EntityDataTableTest.php
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
    echo "[SKIP] PostgreSQL not reachable — all EntityDataTableTest cases skipped.\n";
    echo "       Configure backend/.env with valid DB_* vars and run 002_core.sql.\n";
    echo "----------------------------------------\n";
    echo "Resultado: 0 passed, 0 failed (skipped)\n";
    exit(0);
}

// ---------------------------------------------------------------------------
// Tests
// ---------------------------------------------------------------------------

TestSuite::run('entity_data table exists after migration', function (): void {
    $pdo  = Database::connection();
    $stmt = $pdo->query(
        "SELECT EXISTS (
            SELECT 1 FROM information_schema.tables
            WHERE table_schema = 'public'
            AND   table_name   = 'entity_data'
        ) AS exists"
    );
    assertTrue($stmt !== false, QUERY_EXECUTE_MSG);
    $row = $stmt->fetch();
    assertTrue($row !== false && $row['exists'] === true, 'entity_data table must exist');
});

TestSuite::run('entity_data has expected columns', function (): void {
    $pdo  = Database::connection();
    $stmt = $pdo->query(
        "SELECT column_name FROM information_schema.columns
         WHERE table_schema = 'public' AND table_name = 'entity_data'
         ORDER BY ordinal_position"
    );
    assertTrue($stmt !== false, QUERY_EXECUTE_MSG);
    $columns = array_column($stmt->fetchAll(), 'column_name');
    foreach (['id', 'entity_slug', 'owner_id', 'content', 'created_at', 'updated_at', 'deleted_at'] as $col) {
        assertTrue(in_array($col, $columns, true), "Column '{$col}' must exist");
    }
});

TestSuite::run('entity_data deleted_at column is nullable (soft delete)', function (): void {
    $pdo  = Database::connection();
    $stmt = $pdo->query(
        "SELECT is_nullable FROM information_schema.columns
         WHERE table_schema = 'public'
           AND table_name   = 'entity_data'
           AND column_name  = 'deleted_at'"
    );
    assertTrue($stmt !== false, QUERY_EXECUTE_MSG);
    $row = $stmt->fetch();
    assertTrue($row !== false, 'deleted_at column must exist');
    assertTrue($row['is_nullable'] === 'YES', 'deleted_at must be nullable for soft delete');
});

TestSuite::run('entity_data has GIN index on content', function (): void {
    $pdo  = Database::connection();
    $stmt = $pdo->query(
        "SELECT COUNT(*) AS cnt
         FROM pg_indexes
         WHERE schemaname = 'public'
           AND tablename  = 'entity_data'
           AND indexname  = 'idx_entity_data_content_gin'"
    );
    assertTrue($stmt !== false, QUERY_EXECUTE_MSG);
    $row = $stmt->fetch();
    assertTrue((int) ($row['cnt'] ?? 0) >= 1, 'Expected GIN index idx_entity_data_content_gin');
});

TestSuite::run('entity_data has btree index on entity_slug', function (): void {
    $pdo  = Database::connection();
    $stmt = $pdo->query(
        "SELECT COUNT(*) AS cnt
         FROM pg_indexes
         WHERE schemaname = 'public'
           AND tablename  = 'entity_data'
           AND indexname  = 'idx_entity_data_slug'"
    );
    assertTrue($stmt !== false, QUERY_EXECUTE_MSG);
    $row = $stmt->fetch();
    assertTrue((int) ($row['cnt'] ?? 0) >= 1, 'Expected index idx_entity_data_slug');
});
