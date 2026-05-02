<?php

/**
 * MigrationIdempotenceTest — Verifies all migrations are idempotent.
 *
 * Tests that running each migration file twice does not cause errors and does
 * not alter existing data. This is critical for deployment safety.
 *
 * Run:
 *   php backend/tests/integration/MigrationIdempotenceTest.php
 */

declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__, 2));

require_once BASE_PATH . '/tests/unit/helpers.php';
require_once BASE_PATH . '/src/exceptions/DatabaseException.php';
require_once BASE_PATH . '/src/core/Database.php';

use Xestify\core\Database;
use Xestify\exceptions\DatabaseException;

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
    echo "       Configure backend/.env with valid DB_* vars and run the migrations in order (001-005).\n";
    echo "----------------------------------------\n";
    echo "Resultado: 0 passed, 0 failed (skipped)\n";
    exit(0);
}

// ---------------------------------------------------------------------------
// Tests
// ---------------------------------------------------------------------------

TestSuite::run('all migration tables exist after running 001-005', function (): void {
    $pdo = Database::connection();

    $tables = [
        'users',
        'plugin_entity_data',
        'plugins',
        'plugin_hooks',
        'plugin_extension_data',
    ];
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

TestSuite::run('re-running all migrations does not cause errors', function (): void {
    $psqlPath = 'C:\\Program Files\\PostgreSQL\\18\\bin\\psql.exe';
    $migrations = [
        '001_users.sql',
        '002_plugin_entity_data.sql',
        '003_plugins.sql',
        '004_plugin_hooks.sql',
        '005_plugin_extension_data.sql',
    ];

    foreach ($migrations as $file) {
        $migrationFile = BASE_PATH . '/database/migrations/' . $file;
        $cmd = "\"$psqlPath\" -v client_min_messages=warning -U postgres -d xestify_dev -f \"$migrationFile\" 2>&1";
        exec($cmd, $output, $exitCode);
        assertTrue(
            $exitCode === 0,
            "$file should be idempotent; psql exited with code: $exitCode\nOutput: " . implode("\n", $output)
        );
    }
});

TestSuite::run('idempotent re-run preserves existing data', function (): void {
    $pdo = Database::connection();

    // Insert a test plugin row into plugins (entity type).
    $testSlug = 'idempotence_test_' . uniqid();
    $pdo->prepare(
        'INSERT INTO plugins (slug, name, plugin_type, version, status)
         VALUES (:slug, :name, :type, :version, :status)
         ON CONFLICT (slug) DO NOTHING'
    )->execute([
        ':slug'    => $testSlug,
        ':name'    => 'Idempotence Test Entity',
        ':type'    => 'entity',
        ':version' => '1.0.0',
        ':status'  => 'active',
    ]);

    // Retrieve row count before re-running migrations.
    $stmt = $pdo->query("SELECT COUNT(*) AS cnt FROM plugins WHERE slug = '$testSlug'");
    $rowBefore = $stmt->fetch();
    $countBefore = (int) ($rowBefore['cnt'] ?? 0);
    assertTrue($countBefore === 1, 'Test row must be inserted');

    // Re-run 005_plugins.sql (idempotent, CREATE TABLE IF NOT EXISTS).
    $psqlPath = 'C:\\Program Files\\PostgreSQL\\18\\bin\\psql.exe';
    $migrationFile = BASE_PATH . '/database/migrations/003_plugins.sql';
    $cmd = "\"$psqlPath\" -v client_min_messages=warning -U postgres -d xestify_dev -f \"$migrationFile\" 2>&1";
    exec($cmd, $output, $exitCode);
    assertTrue($exitCode === 0, 'Migration re-run must succeed');

    // Verify row count after migration.
    $stmt = $pdo->query("SELECT COUNT(*) AS cnt FROM plugins WHERE slug = '$testSlug'");
    $rowAfter = $stmt->fetch();
    $countAfter = (int) ($rowAfter['cnt'] ?? 0);
    assertTrue($countAfter === 1, 'Test row must still be present; no duplicates should exist');

    // Clean up.
    $pdo->exec("DELETE FROM plugins WHERE slug = '$testSlug'");
});

// ---------------------------------------------------------------------------

TestSuite::summary();
exit(TestSuite::exitCode());

