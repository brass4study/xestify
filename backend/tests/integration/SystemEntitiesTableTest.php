<?php

/**
 * SystemEntitiesTableTest — Integration tests.
 *
 * Verifies that the system_entities table was created correctly by migration
 * 002_core.sql. Requires a live PostgreSQL connection.
 *
 * Run:
 *   php backend/tests/integration/SystemEntitiesTableTest.php
 */

declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__, 2));

require_once BASE_PATH . '/tests/unit/helpers.php';
require_once BASE_PATH . '/src/Exceptions/DatabaseException.php';
require_once BASE_PATH . '/src/Core/Database.php';

use Xestify\Core\Database;
use Xestify\Exceptions\DatabaseException;

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
    echo "[SKIP] PostgreSQL not reachable — all SystemEntitiesTableTest cases skipped.\n";
    echo "       Configure backend/.env with valid DB_* vars and run 002_core.sql.\n";
    echo "----------------------------------------\n";
    echo "Resultado: 0 passed, 0 failed (skipped)\n";
    exit(0);
}

// ---------------------------------------------------------------------------
// Tests
// ---------------------------------------------------------------------------

TestSuite::run('system_entities table exists after migration', function (): void {
    $pdo  = Database::connection();
    $stmt = $pdo->query(
        "SELECT EXISTS (
            SELECT 1 FROM information_schema.tables
            WHERE table_schema = 'public'
            AND   table_name   = 'system_entities'
        ) AS exists"
    );
    assertTrue($stmt !== false, 'Query should execute');
    $row = $stmt->fetch();
    assertTrue($row !== false && $row['exists'] === true, 'system_entities table must exist');
});

TestSuite::run('system_entities has expected columns', function (): void {
    $pdo  = Database::connection();
    $stmt = $pdo->query(
        "SELECT column_name FROM information_schema.columns
         WHERE table_schema = 'public' AND table_name = 'system_entities'
         ORDER BY ordinal_position"
    );
    assertTrue($stmt !== false, 'Query should execute');
    $columns = array_column($stmt->fetchAll(), 'column_name');
    foreach (['id', 'slug', 'name', 'source_plugin_slug', 'is_active', 'created_at', 'updated_at'] as $col) {
        assertTrue(in_array($col, $columns, true), "Column '{$col}' must exist");
    }
});

TestSuite::run('system_entities slug column has unique constraint', function (): void {
    $pdo = Database::connection();
    $stmt = $pdo->query(
        "SELECT COUNT(*) AS cnt
         FROM information_schema.table_constraints tc
         JOIN information_schema.constraint_column_usage ccu
           ON tc.constraint_name = ccu.constraint_name
          AND tc.table_schema    = ccu.table_schema
         WHERE tc.constraint_type = 'UNIQUE'
           AND tc.table_schema    = 'public'
           AND tc.table_name      = 'system_entities'
           AND ccu.column_name    = 'slug'"
    );
    assertTrue($stmt !== false, 'Query should execute');
    $row = $stmt->fetch();
    assertTrue((int) ($row['cnt'] ?? 0) >= 1, 'slug must have a UNIQUE constraint');
});

// ---------------------------------------------------------------------------

TestSuite::summary();
exit(TestSuite::exitCode());
