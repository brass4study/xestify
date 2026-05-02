<?php

/**
 * PluginsRegistryTableTest — Integration tests.
 *
 * Verifies that the plugins table was created correctly by migration
 * 005_plugins.sql. Requires a live PostgreSQL connection.
 *
 * Run:
 *   php backend/tests/integration/PluginsRegistryTableTest.php
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
    echo "[SKIP] PostgreSQL not reachable — all PluginsRegistryTableTest cases skipped.\n";
    echo "       Configure backend/.env with valid DB_* vars and run the migrations in order (001–009).\n";
    echo "----------------------------------------\n";
    echo "Resultado: 0 passed, 0 failed (skipped)\n";
    exit(0);
}

// ---------------------------------------------------------------------------
// Tests
// ---------------------------------------------------------------------------

TestSuite::run('plugins table exists after migration', function (): void {
    $pdo  = Database::connection();
    $stmt = $pdo->query(
        "SELECT EXISTS (
            SELECT 1 FROM information_schema.tables
            WHERE table_schema = 'public'
            AND   table_name   = 'plugins'
        ) AS exists"
    );
    assertTrue($stmt !== false, QUERY_EXECUTE_MSG);
    $row = $stmt->fetch();
    assertTrue($row !== false && $row['exists'] === true, 'plugins table must exist');
});

TestSuite::run('plugins has expected columns', function (): void {
    $pdo  = Database::connection();
    $stmt = $pdo->query(
        "SELECT column_name FROM information_schema.columns
         WHERE table_schema = 'public' AND table_name = 'plugins'
         ORDER BY ordinal_position"
    );
    assertTrue($stmt !== false, QUERY_EXECUTE_MSG);
    $columns = array_column($stmt->fetchAll(), 'column_name');
    foreach (['id', 'slug', 'name', 'plugin_type', 'version', 'status', 'schema_version', 'schema_json', 'installed_at', 'updated_at'] as $col) {
        assertTrue(in_array($col, $columns, true), "Column '{$col}' must exist");
    }
});

TestSuite::run('plugins has idx_plugins_type_status index', function (): void {
    $pdo  = Database::connection();
    $stmt = $pdo->query(
        "SELECT COUNT(*) AS cnt
         FROM pg_indexes
         WHERE schemaname = 'public'
           AND tablename  = 'plugins'
           AND indexname  = 'idx_plugins_type_status'"
    );
    assertTrue($stmt !== false, QUERY_EXECUTE_MSG);
    $row = $stmt->fetch();
    assertTrue((int) ($row['cnt'] ?? 0) >= 1, 'Expected index idx_plugins_type_status');
});

TestSuite::run('plugins slug has unique constraint', function (): void {
    $pdo  = Database::connection();
    $stmt = $pdo->query(
        "SELECT COUNT(*) AS cnt
         FROM information_schema.table_constraints tc
         JOIN information_schema.constraint_column_usage ccu
           ON tc.constraint_name = ccu.constraint_name
          AND tc.table_schema    = ccu.table_schema
         WHERE tc.constraint_type = 'UNIQUE'
           AND tc.table_schema    = 'public'
           AND tc.table_name      = 'plugins'
           AND ccu.column_name    = 'slug'"
    );
    assertTrue($stmt !== false, QUERY_EXECUTE_MSG);
    $row = $stmt->fetch();
    assertTrue((int) ($row['cnt'] ?? 0) >= 1, 'slug must have a UNIQUE constraint');
});

TestSuite::run('plugins plugin_type has CHECK constraint (entity|extension)', function (): void {
    $pdo = Database::connection();
    $stmt = $pdo->query(
        "SELECT COUNT(*) AS cnt
         FROM information_schema.check_constraints cc
         JOIN information_schema.table_constraints tc
           ON cc.constraint_name   = tc.constraint_name
          AND cc.constraint_schema = tc.table_schema
         WHERE tc.table_schema = 'public'
           AND tc.table_name   = 'plugins'
           AND cc.check_clause LIKE '%entity%'
           AND cc.check_clause LIKE '%extension%'"
    );
    assertTrue($stmt !== false, QUERY_EXECUTE_MSG);
    $row = $stmt->fetch();
    assertTrue((int) ($row['cnt'] ?? 0) >= 1, 'plugin_type must have a CHECK constraint with entity/extension');
});

TestSuite::run('plugins status has CHECK constraint (active|inactive|error)', function (): void {
    $pdo = Database::connection();
    $stmt = $pdo->query(
        "SELECT COUNT(*) AS cnt
         FROM information_schema.check_constraints cc
         JOIN information_schema.table_constraints tc
           ON cc.constraint_name   = tc.constraint_name
          AND cc.constraint_schema = tc.table_schema
         WHERE tc.table_schema = 'public'
           AND tc.table_name   = 'plugins'
           AND cc.check_clause LIKE '%active%'
           AND cc.check_clause LIKE '%error%'"
    );
    assertTrue($stmt !== false, QUERY_EXECUTE_MSG);
    $row = $stmt->fetch();
    assertTrue((int) ($row['cnt'] ?? 0) >= 1, 'status must have a CHECK constraint with active/inactive/error');
});

// ---------------------------------------------------------------------------

TestSuite::summary();
exit(TestSuite::exitCode());
