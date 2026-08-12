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
    echo "       Configure backend/.env with valid DB_* vars and run the migrations in order (001-006).\n";
    echo "----------------------------------------\n";
    echo "Resultado: 0 passed, 0 failed (skipped)\n";
    exit(0);
}

// ---------------------------------------------------------------------------
// psql helper — resolves the executable via PSQL_PATH (fallback: 'psql' on
// PATH) and reuses the same DB_* vars as Database::connection(), instead of
// a hardcoded Windows path/user/database tied to one developer's machine.
// ---------------------------------------------------------------------------

/**
 * @return array{0: array<int, string>, 1: int} [$output, $exitCode]
 */
function runPsqlMigration(string $migrationFile): array
{
    $psql = $_ENV['PSQL_PATH'] ?? 'psql';
    $host = $_ENV['DB_HOST'] ?? 'localhost';
    $port = $_ENV['DB_PORT'] ?? '5432';
    $name = $_ENV['DB_NAME'] ?? 'xestify_dev';
    $user = $_ENV['DB_USER'] ?? 'postgres';
    $pass = $_ENV['DB_PASSWORD'] ?? '';

    if ($pass !== '') {
        putenv('PGPASSWORD=' . $pass);
    }

    $cmd = escapeshellarg($psql)
        . ' -h ' . escapeshellarg($host)
        . ' -p ' . escapeshellarg($port)
        . ' -U ' . escapeshellarg($user)
        . ' -d ' . escapeshellarg($name)
        . ' -v client_min_messages=warning -f ' . escapeshellarg($migrationFile)
        . ' 2>&1';

    $output = [];
    $exitCode = 0;
    exec($cmd, $output, $exitCode);

    return [$output, $exitCode];
}

// ---------------------------------------------------------------------------
// Tests
// ---------------------------------------------------------------------------

TestSuite::run('all migration tables exist after running 001-006', function (): void {
    $pdo = Database::connection();

    $tables = [
        'users',
        'plugin_entity_data',
        'plugins',
        'plugin_extension_data',
        'plugin_update_history',
        'configuration',
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
    $migrations = [
        '001_users.sql',
        '002_plugin_entity_data.sql',
        '003_plugins.sql',
        '004_plugin_extension_data.sql',
        '005_plugin_update_history.sql',
        '006_configuration.sql',
    ];

    foreach ($migrations as $file) {
        $migrationFile = BASE_PATH . '/database/migrations/' . $file;
        [$output, $exitCode] = runPsqlMigration($migrationFile);
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

    // Re-run 003_plugins.sql (idempotent, CREATE TABLE IF NOT EXISTS).
    $migrationFile = BASE_PATH . '/database/migrations/003_plugins.sql';
    [, $exitCode] = runPsqlMigration($migrationFile);
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

