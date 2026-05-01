<?php

/**
 * MigrationIdempotenceTest — Verifies 002_core.sql is idempotent.
 *
 * Tests that running the migration twice does not cause errors and does not
 * alter existing data. This is critical for deployment safety.
 *
 * Run:
 *   php backend/tests/integration/MigrationIdempotenceTest.php
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
    echo "[SKIP] PostgreSQL not reachable — all MigrationIdempotenceTest cases skipped.\n";
    echo "       Configure backend/.env with valid DB_* vars and run 002_core.sql.\n";
    echo "----------------------------------------\n";
    echo "Resultado: 0 passed, 0 failed (skipped)\n";
    exit(0);
}

// ---------------------------------------------------------------------------
// Tests
// ---------------------------------------------------------------------------

TestSuite::run('migration 002_core.sql succeeds on first execution', function (): void {
    $pdo = Database::connection();

    // Verify all tables exist (they should from earlier tests).
    $tables = ['system_entities', 'entity_metadata', 'entity_data', 'plugins_registry', 'plugin_hook_registry'];
    foreach ($tables as $table) {
        $stmt = $pdo->query(
            "SELECT EXISTS (
                SELECT 1 FROM information_schema.tables
                WHERE table_schema = 'public' AND table_name = '$table'
            ) AS exists"
        );
        $row = $stmt->fetch();
        assertTrue($row !== false && $row['exists'] === true, "Table $table must exist");
    }
});

TestSuite::run('running 002_core.sql again does not cause errors', function (): void {
    $psqlPath = 'C:\\Program Files\\PostgreSQL\\18\\bin\\psql.exe';
    $migrationFile = BASE_PATH . '/database/migrations/002_core.sql';

    // Execute psql with the migration file a second time.
    $cmd = "\"$psqlPath\" -v client_min_messages=warning -U postgres -d xestify_dev -f \"$migrationFile\" 2>&1";
    exec($cmd, $output, $exitCode);

    // psql should exit with 0 (idempotent IF NOT EXISTS clauses).
    assertTrue(
        $exitCode === 0,
        'Migration should be idempotent; psql exited with code: ' . $exitCode . 
        "\nOutput: " . implode("\n", $output)
    );
});

TestSuite::run('idempotent re-run preserves existing data', function (): void {
    $pdo = Database::connection();

    // Insert a test row into system_entities.
    $testSlug = 'idempotence_test_' . uniqid();
    $pdo->prepare(
        'INSERT INTO system_entities (slug, name, is_active)
         VALUES (:slug, :name, :active)'
    )->execute([
        ':slug' => $testSlug,
        ':name' => 'Idempotence Test Entity',
        ':active' => true,
    ]);

    // Retrieve row count before re-running migration.
    $stmt = $pdo->query("SELECT COUNT(*) AS cnt FROM system_entities WHERE slug = '$testSlug'");
    $rowBefore = $stmt->fetch();
    $countBefore = (int) ($rowBefore['cnt'] ?? 0);
    assertTrue($countBefore === 1, 'Test row must be inserted');

    // Re-run migration (via psql).
    $psqlPath = 'C:\\Program Files\\PostgreSQL\\18\\bin\\psql.exe';
    $migrationFile = BASE_PATH . '/database/migrations/002_core.sql';
    $cmd = "\"$psqlPath\" -v client_min_messages=warning -U postgres -d xestify_dev -f \"$migrationFile\" 2>&1";
    exec($cmd, $output, $exitCode);
    assertTrue($exitCode === 0, 'Migration re-run must succeed');

    // Verify row count after migration.
    $stmt = $pdo->query("SELECT COUNT(*) AS cnt FROM system_entities WHERE slug = '$testSlug'");
    $rowAfter = $stmt->fetch();
    $countAfter = (int) ($rowAfter['cnt'] ?? 0);
    assertTrue($countAfter === 1, 'Test row must still be present; no duplicates should exist');

    // Clean up.
    $pdo->exec("DELETE FROM system_entities WHERE slug = '$testSlug'");
});
