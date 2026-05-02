<?php

/**
 * EntityMetadataTableTest — Integration tests.
 *
 * Verifies that plugin_entity_metadata no longer exists (merged into plugins)
 * and that the plugins table has the schema_json and schema_version columns.
 * Requires a live PostgreSQL connection.
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
    echo "       Configure backend/.env with valid DB_* vars and run the migrations in order (001–008).\n";
    echo "----------------------------------------\n";
    echo "Resultado: 0 passed, 0 failed (skipped)\n";
    exit(0);
}

// ---------------------------------------------------------------------------
// Tests
// ---------------------------------------------------------------------------

TestSuite::run('plugin_entity_metadata table does not exist after refactor migration', function (): void {
    $pdo  = Database::connection();
    $stmt = $pdo->query(
        "SELECT EXISTS (
            SELECT 1 FROM information_schema.tables
            WHERE table_schema = 'public'
            AND   table_name   = 'plugin_entity_metadata'
        ) AS exists"
    );
    assertTrue($stmt !== false, QUERY_EXECUTE_MSG);
    $row = $stmt->fetch();
    assertTrue($row !== false && $row['exists'] === false, 'plugin_entity_metadata must not exist after merge into plugins');
});

TestSuite::run('plugins table has schema_json column', function (): void {
    $pdo  = Database::connection();
    $stmt = $pdo->query(
        "SELECT column_name FROM information_schema.columns
         WHERE table_schema = 'public' AND table_name = 'plugins' AND column_name = 'schema_json'"
    );
    assertTrue($stmt !== false, QUERY_EXECUTE_MSG);
    $row = $stmt->fetch();
    assertTrue($row !== false, 'plugins.schema_json column must exist');
});

TestSuite::run('plugins table has schema_version column', function (): void {
    $pdo  = Database::connection();
    $stmt = $pdo->query(
        "SELECT column_name FROM information_schema.columns
         WHERE table_schema = 'public' AND table_name = 'plugins' AND column_name = 'schema_version'"
    );
    assertTrue($stmt !== false, QUERY_EXECUTE_MSG);
    $row = $stmt->fetch();
    assertTrue($row !== false, 'plugins.schema_version column must exist');
});

TestSuite::run('plugins schema_json accepts null (extension plugins without schema)', function (): void {
    $pdo = Database::connection();
    $stmt = $pdo->query(
        "SELECT is_nullable FROM information_schema.columns
         WHERE table_schema = 'public' AND table_name = 'plugins' AND column_name = 'schema_json'"
    );
    assertTrue($stmt !== false, QUERY_EXECUTE_MSG);
    $row = $stmt->fetch();
    assertTrue($row !== false && $row['is_nullable'] === 'YES', 'schema_json must be nullable');
});

// ---------------------------------------------------------------------------

TestSuite::summary();
exit(TestSuite::exitCode());
