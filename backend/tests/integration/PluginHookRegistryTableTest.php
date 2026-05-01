<?php

/**
 * PluginHookRegistryTableTest — Integration tests.
 *
 * Verifies that the plugin_hook_registry table was created correctly by
 * migration 002_core.sql. Requires a live PostgreSQL connection.
 *
 * Run:
 *   php backend/tests/integration/PluginHookRegistryTableTest.php
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
    echo "[SKIP] PostgreSQL not reachable — all PluginHookRegistryTableTest cases skipped.\n";
    echo "       Configure backend/.env with valid DB_* vars and run 002_core.sql.\n";
    echo "----------------------------------------\n";
    echo "Resultado: 0 passed, 0 failed (skipped)\n";
    exit(0);
}

// ---------------------------------------------------------------------------
// Tests
// ---------------------------------------------------------------------------

TestSuite::run('plugin_hook_registry table exists after migration', function (): void {
    $pdo  = Database::connection();
    $stmt = $pdo->query(
        "SELECT EXISTS (
            SELECT 1 FROM information_schema.tables
            WHERE table_schema = 'public'
            AND   table_name   = 'plugin_hook_registry'
        ) AS exists"
    );
    assertTrue($stmt !== false, 'Query should execute');
    $row = $stmt->fetch();
    assertTrue($row !== false && $row['exists'] === true, 'plugin_hook_registry table must exist');
});

TestSuite::run('plugin_hook_registry has expected columns', function (): void {
    $pdo  = Database::connection();
    $stmt = $pdo->query(
        "SELECT column_name FROM information_schema.columns
         WHERE table_schema = 'public' AND table_name = 'plugin_hook_registry'
         ORDER BY ordinal_position"
    );
    assertTrue($stmt !== false, 'Query should execute');
    $columns = array_column($stmt->fetchAll(), 'column_name');
    foreach (['id', 'plugin_slug', 'target_entity_slug', 'hook_name', 'priority', 'enabled'] as $col) {
        assertTrue(in_array($col, $columns, true), "Column '{$col}' must exist");
    }
});

TestSuite::run('plugin_hook_registry priority defaults to 10', function (): void {
    $pdo  = Database::connection();
    $stmt = $pdo->query(
        "SELECT column_default FROM information_schema.columns
         WHERE table_schema = 'public'
           AND table_name   = 'plugin_hook_registry'
           AND column_name  = 'priority'"
    );
    assertTrue($stmt !== false, 'Query should execute');
    $row = $stmt->fetch();
    assertTrue($row !== false, 'priority column must exist');
    assertTrue($row['column_default'] === '10', "priority default must be 10, got: {$row['column_default']}");
});

TestSuite::run('plugin_hook_registry enabled defaults to true', function (): void {
    $pdo  = Database::connection();
    $stmt = $pdo->query(
        "SELECT column_default FROM information_schema.columns
         WHERE table_schema = 'public'
           AND table_name   = 'plugin_hook_registry'
           AND column_name  = 'enabled'"
    );
    assertTrue($stmt !== false, 'Query should execute');
    $row = $stmt->fetch();
    assertTrue($row !== false, 'enabled column must exist');
    assertTrue($row['column_default'] === 'true', "enabled default must be true, got: {$row['column_default']}");
});

TestSuite::run('plugin_hook_registry has composite index on (target_entity_slug, hook_name)', function (): void {
    $pdo  = Database::connection();
    $stmt = $pdo->query(
        "SELECT COUNT(*) AS cnt
         FROM pg_indexes
         WHERE schemaname = 'public'
           AND tablename  = 'plugin_hook_registry'
           AND indexname  = 'idx_plugin_hook_registry_target_hook'"
    );
    assertTrue($stmt !== false, 'Query should execute');
    $row = $stmt->fetch();
    assertTrue((int) ($row['cnt'] ?? 0) >= 1, 'Expected index idx_plugin_hook_registry_target_hook');
});
