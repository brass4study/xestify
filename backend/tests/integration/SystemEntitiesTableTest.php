<?php

/**
 * SystemEntitiesTableTest — Integration tests.
 *
 * After migration 010_drop_system_entities.sql, the system_entities table must
 * no longer exist. Entity types are registered in the plugins table instead.
 *
 * Run:
 *   php backend/tests/integration/SystemEntitiesTableTest.php
 */

declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__, 2));

require_once BASE_PATH . '/tests/unit/helpers.php';
require_once BASE_PATH . '/src/exceptions/DatabaseException.php';
require_once BASE_PATH . '/src/core/Database.php';

use Xestify\core\Database;
use Xestify\exceptions\DatabaseException;

const MSG_QUERY_EXECUTE = 'Query should execute';

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
    echo "       Configure backend/.env with valid DB_* vars and run the migrations.\n";
    echo "----------------------------------------\n";
    echo "Resultado: 0 passed, 0 failed (skipped)\n";
    exit(0);
}

// ---------------------------------------------------------------------------
// Tests
// ---------------------------------------------------------------------------

TestSuite::run('system_entities table does not exist after migration 010', function (): void {
    $pdo  = Database::connection();
    $stmt = $pdo->query(
        "SELECT EXISTS (
            SELECT 1 FROM information_schema.tables
            WHERE table_schema = 'public'
            AND   table_name   = 'system_entities'
        ) AS exists"
    );
    assertTrue($stmt !== false, MSG_QUERY_EXECUTE);
    $row = $stmt->fetch();
    assertTrue($row !== false && $row['exists'] === false, 'system_entities table must not exist after migration 010');
});

TestSuite::run('plugins table has entity-type rows as catalog', function (): void {
    $pdo  = Database::connection();
    $stmt = $pdo->query(
        "SELECT COUNT(*) AS cnt FROM plugins WHERE plugin_type = 'entity'"
    );
    assertTrue($stmt !== false, MSG_QUERY_EXECUTE);
    $row = $stmt->fetch();
    assertTrue((int) ($row['cnt'] ?? 0) >= 1, 'plugins must contain at least one entity-type row');
});

TestSuite::run('plugins entity rows have required fields', function (): void {
    $pdo  = Database::connection();
    $stmt = $pdo->query(
        "SELECT slug, name, plugin_type, status FROM plugins
         WHERE plugin_type = 'entity' AND status = 'active' LIMIT 1"
    );
    assertTrue($stmt !== false, MSG_QUERY_EXECUTE);
    $row = $stmt->fetch();
    assertTrue($row !== false, 'At least one entity plugin row must exist');
    assertTrue(!empty($row['slug']), 'slug must not be empty');
    assertTrue(!empty($row['name']), 'name must not be empty');
    assertEquals('entity', $row['plugin_type']);
    assertEquals('active', $row['status']);
});

// ---------------------------------------------------------------------------

TestSuite::summary();
exit(TestSuite::exitCode());
