<?php

/**
 * PluginUpdateHistoryTableTest — Integration tests.
 *
 * Verifies that the plugin_update_history table was created correctly by
 * migration 005_plugin_update_history.sql. Requires a live PostgreSQL connection.
 *
 * Run:
 *   php backend/tests/integration/PluginUpdateHistoryTableTest.php
 */

declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__, 2));

require_once BASE_PATH . '/tests/unit/helpers.php';
require_once BASE_PATH . '/src/exceptions/DatabaseException.php';
require_once BASE_PATH . '/src/core/Database.php';

use Xestify\core\Database;
use Xestify\exceptions\DatabaseException;

const QUERY_EXECUTE_MSG = 'Query should execute';

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

try {
    Database::connection();
} catch (DatabaseException) {
    echo "[SKIP] PostgreSQL not reachable — all PluginUpdateHistoryTableTest cases skipped.\n";
    echo "       Configure backend/.env with valid DB_* vars and run the migrations in order (001-006).\n";
    echo "----------------------------------------\n";
    echo "Resultado: 0 passed, 0 failed (skipped)\n";
    exit(0);
}

TestSuite::run('plugin_update_history table exists after migration', function (): void {
    $pdo = Database::connection();
    $stmt = $pdo->query(
        "SELECT EXISTS (
            SELECT 1 FROM information_schema.tables
            WHERE table_schema = 'public'
            AND   table_name   = 'plugin_update_history'
        ) AS exists"
    );
    assertTrue($stmt !== false, QUERY_EXECUTE_MSG);
    $row = $stmt->fetch();
    assertTrue($row !== false && $row['exists'] === true, 'plugin_update_history table must exist');
});

TestSuite::run('plugin_update_history has expected columns', function (): void {
    $pdo = Database::connection();
    $stmt = $pdo->query(
        "SELECT column_name FROM information_schema.columns
         WHERE table_schema = 'public' AND table_name = 'plugin_update_history'
         ORDER BY ordinal_position"
    );
    assertTrue($stmt !== false, QUERY_EXECUTE_MSG);
    $columns = array_column($stmt->fetchAll(), 'column_name');

    foreach (['id', 'slug', 'name', 'plugin_type', 'version', 'status', 'schema_version', 'schema_json', 'target_version', 'created_at'] as $col) {
        assertTrue(in_array($col, $columns, true), "Column '{$col}' must exist");
    }
});

TestSuite::run('plugin_update_history has slug-created_at index', function (): void {
    $pdo = Database::connection();
    $stmt = $pdo->query(
        "SELECT COUNT(*) AS cnt
         FROM pg_indexes
         WHERE schemaname = 'public'
           AND tablename  = 'plugin_update_history'
           AND indexname  = 'idx_plugin_update_history_slug_created_at'"
    );
    assertTrue($stmt !== false, QUERY_EXECUTE_MSG);
    $row = $stmt->fetch();
    assertTrue((int) ($row['cnt'] ?? 0) >= 1, 'Expected index idx_plugin_update_history_slug_created_at');
});

TestSuite::summary();
exit(TestSuite::exitCode());
