<?php

/**
 * DatabaseTest — Integration tests.
 *
 * These tests require a real PostgreSQL connection. If the connection
 * cannot be established, every test is skipped gracefully so that the
 * CI suite does not fail in environments without a database.
 *
 * Prerequisites:
 *   1. backend/.env exists with valid DB_* vars.
 *   2. Migration 001_users.sql has been applied.
 *
 * Run:
 *   php backend/tests/integration/DatabaseTest.php
 */

declare(strict_types=1);

// Resolve BASE_PATH so bootstrap.php works standalone.
define('BASE_PATH', dirname(__DIR__, 2));

require_once BASE_PATH . '/tests/unit/helpers.php';
require_once BASE_PATH . '/src/exceptions/DatabaseException.php';
require_once BASE_PATH . '/src/core/Database.php';
require_once BASE_PATH . '/src/database/Seeders/UserSeeder.php';

use Xestify\core\Database;
use Xestify\database\seeders\UserSeeder;
use Xestify\exceptions\DatabaseException;

// ---------------------------------------------------------------------------
// Load .env so $_ENV is populated (mirrors bootstrap.php, no full bootstrap)
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
// Connectivity probe — skip all tests if DB is unreachable
// ---------------------------------------------------------------------------

$dbAvailable = true;
try {
    Database::connection();
} catch (DatabaseException) {
    $dbAvailable = false;
    echo "[SKIP] PostgreSQL not reachable — all DatabaseTest cases skipped.\n";
    echo "       Configure backend/.env with valid DB_* vars and run the migration.\n";
    echo "----------------------------------------\n";
    echo "Resultado: 0 passed, 0 failed (skipped)\n";
    exit(0);
}

// ---------------------------------------------------------------------------
// Helper: reset the Database singleton between tests so each test starts clean
// ---------------------------------------------------------------------------

function resetDatabaseSingleton(): void
{
    $resetStaticProperty = static function (): void {
        self::$pdo = null;
    };
    \Closure::bind($resetStaticProperty, null, Database::class)();
}

// ---------------------------------------------------------------------------
// Tests
// ---------------------------------------------------------------------------

TestSuite::run('connection() returns a PDO instance', function (): void {
    resetDatabaseSingleton();
    $pdo = Database::connection();
    assertTrue($pdo instanceof \PDO, 'Expected PDO instance');
});

TestSuite::run('connection() is a singleton (same instance on repeat calls)', function (): void {
    resetDatabaseSingleton();
    $a = Database::connection();
    $b = Database::connection();
    assertTrue($a === $b, 'Should return the same PDO instance');
});

TestSuite::run('PDO has ERRMODE_EXCEPTION set', function (): void {
    resetDatabaseSingleton();
    $pdo  = Database::connection();
    $mode = $pdo->getAttribute(\PDO::ATTR_ERRMODE);
    assertEquals(\PDO::ERRMODE_EXCEPTION, $mode, 'ERRMODE_EXCEPTION expected');
});

TestSuite::run('PDO has FETCH_ASSOC as default fetch mode', function (): void {
    resetDatabaseSingleton();
    $pdo  = Database::connection();
    $mode = $pdo->getAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE);
    assertEquals(\PDO::FETCH_ASSOC, $mode, 'FETCH_ASSOC expected');
});

TestSuite::run('users table exists after migration', function (): void {
    $pdo  = Database::connection();
    $stmt = $pdo->query(
        "SELECT EXISTS (
            SELECT 1 FROM information_schema.tables
            WHERE table_schema = 'public'
            AND   table_name   = 'users'
        ) AS exists"
    );
    assertTrue($stmt !== false, 'Query should execute');
    $row = $stmt->fetch();
    assertTrue($row !== false && $row['exists'] === true, 'users table must exist');
});

TestSuite::run('users table has expected columns', function (): void {
    $pdo  = Database::connection();
    $stmt = $pdo->query(
        "SELECT column_name FROM information_schema.columns
         WHERE table_schema = 'public' AND table_name = 'users'
         ORDER BY ordinal_position"
    );
    assertTrue($stmt !== false, 'Query should execute');
    $columns = array_column($stmt->fetchAll(), 'column_name');
    foreach (['id', 'email', 'password_hash', 'roles', 'created_at'] as $col) {
        assertTrue(in_array($col, $columns, true), "Column '{$col}' must exist");
    }
});

TestSuite::run('UserSeeder::seedIfEmpty inserts admin when table is empty', function (): void {
    $pdo = Database::connection();

    // Backup and empty the table
    $pdo->exec('BEGIN');
    $pdo->exec('DELETE FROM users');

    UserSeeder::seedIfEmpty();

    $stmt = $pdo->prepare('SELECT email, roles FROM users WHERE email = :email');
    $stmt->execute([':email' => 'admin@xestify.local']);
    $row = $stmt->fetch();

    $pdo->exec('ROLLBACK');

    assertTrue($row !== false, 'Admin row should exist after seeder');
    assertEquals('admin@xestify.local', $row['email'] ?? null, 'email mismatch');
    $roles = json_decode((string) ($row['roles'] ?? '[]'), true);
    assertTrue(in_array('admin', $roles, true), 'roles should contain "admin"');
});

TestSuite::run('UserSeeder::seedIfEmpty does not insert when table already has rows', function (): void {
    $pdo = Database::connection();

    // Ensure at least one row exists (from previous test rollback, use our own)
    $pdo->exec('BEGIN');
    $pdo->exec('DELETE FROM users');
    $pdo->prepare(
        'INSERT INTO users (email, password_hash, roles) VALUES (:e, :h, :r)'
    )->execute([':e' => 'existing@xestify.local', ':h' => 'hash', ':r' => '["user"]']);

    $countBefore = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();

    UserSeeder::seedIfEmpty();

    $countAfter = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();

    $pdo->exec('ROLLBACK');

    assertEquals($countBefore, $countAfter, 'Seeder should not insert when table has rows');
});

TestSuite::run('connection() throws DatabaseException on wrong database name', function (): void {
    // Using a non-existent database name forces an immediate error without TCP timeout.
    $original = $_ENV['DB_NAME'] ?? 'xestify_dev';
    $_ENV['DB_NAME'] = 'xestify_db_that_does_not_exist';

    resetDatabaseSingleton();

    $threw = false;
    try {
        Database::connection();
    } catch (DatabaseException) {
        $threw = true;
    }

    $_ENV['DB_NAME'] = $original;
    resetDatabaseSingleton(); // restore for subsequent tests

    assertTrue($threw, 'Should throw DatabaseException on non-existent database');
});

// ---------------------------------------------------------------------------

TestSuite::summary();
exit(TestSuite::exitCode());
