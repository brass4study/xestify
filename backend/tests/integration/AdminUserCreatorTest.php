<?php

/**
 * AdminUserCreatorTest — Integration tests.
 *
 * Verifies the class behind `tools/setup/install.php` (step "Administrador
 * real") and `tools/setup/create-admin-user.php`: it creates a non-seed
 * admin (the only account able to log in when APP_DEBUG=false), reports
 * whether such an admin already exists, and rejects invalid email, empty
 * name, short password and duplicate email without inserting anything.
 *
 * Every user created here is removed at the end (hard delete by email).
 *
 * Run:
 *   php backend/tests/integration/AdminUserCreatorTest.php
 */

declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__, 2));

require_once BASE_PATH . '/tests/unit/helpers.php';
require_once BASE_PATH . '/tests/helpers/autoload.php';

use Xestify\core\Database;
use Xestify\database\seeders\AdminUserCreator;
use Xestify\exceptions\DatabaseException;
use Xestify\exceptions\SeederException;

const TEST_EMAIL_DOMAIN = '@admin-creator.test';
const VALID_PASSWORD = 'ClaveSegura-2026-xyz';

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
    $pdo = Database::connection();
} catch (DatabaseException) {
    echo "[SKIP] PostgreSQL not reachable — all AdminUserCreatorTest cases skipped.\n";
    echo "       Configure backend/.env with valid DB_* vars and run php tools/setup/install.php.\n";
    echo "----------------------------------------\n";
    echo "Resultado: 0 passed, 0 failed (skipped)\n";
    exit(0);
}

function uniqueEmail(string $prefix): string
{
    return $prefix . '-' . uniqid('', true) . TEST_EMAIL_DOMAIN;
}

function fetchUserByEmail(PDO $pdo, string $email): ?array
{
    $stmt = $pdo->prepare('SELECT email, name, roles, is_seed, deleted_at FROM users WHERE email = :email');
    $stmt->execute([':email' => $email]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return $row === false ? null : $row;
}

function expectSeederException(callable $fn, string $label): void
{
    $threw = false;
    try {
        $fn();
    } catch (SeederException) {
        $threw = true;
    }
    assertTrue($threw, $label);
}

$creator = new AdminUserCreator($pdo);
$createdEmails = [];

try {
    TestSuite::run('creates a real admin (is_seed=false, roles admin) and reports it', function () use ($pdo, $creator, &$createdEmails): void {
        $email = uniqueEmail('first');
        $createdEmails[] = $email;

        $created = $creator->create($email, '  Admin Real  ', VALID_PASSWORD);
        assertEquals($email, $created['email'], 'Returned email mismatch');
        assertEquals('Admin Real', $created['name'], 'Name must be trimmed');
        assertTrue($created['id'] !== '', 'Created id must be returned');

        $row = fetchUserByEmail($pdo, $email);
        assertTrue($row !== null, 'Row must exist');
        assertTrue(in_array($row['is_seed'], [false, 'f', 0, '0'], true), 'Admin must not be a seed user');
        assertEquals(['admin'], json_decode((string) $row['roles'], true), 'Roles must be exactly ["admin"]');
        assertTrue($creator->hasRealAdmin(), 'hasRealAdmin() must be true once a real admin exists');
    });

    TestSuite::run('stored password hash verifies with password_verify', function () use ($pdo, $creator, &$createdEmails): void {
        $email = uniqueEmail('hash');
        $createdEmails[] = $email;
        $creator->create($email, 'Hash Check', VALID_PASSWORD);

        $stmt = $pdo->prepare('SELECT password_hash FROM users WHERE email = :email');
        $stmt->execute([':email' => $email]);
        $hash = (string) $stmt->fetchColumn();
        assertTrue(password_verify(VALID_PASSWORD, $hash), 'Stored hash must verify the original password');
        assertFalse(password_verify('otra-clave-distinta-2026', $hash), 'Stored hash must reject another password');
    });

    TestSuite::run('rejects invalid email, empty name and short password without inserting', function () use ($pdo, $creator): void {
        $email = uniqueEmail('invalid');
        expectSeederException(fn() => $creator->create('not-an-email', 'X', VALID_PASSWORD), 'Invalid email must be rejected');
        expectSeederException(fn() => $creator->create($email, '   ', VALID_PASSWORD), 'Empty name must be rejected');
        expectSeederException(fn() => $creator->create($email, 'X', 'short'), 'Short password must be rejected');
        assertNull(fetchUserByEmail($pdo, $email), 'Nothing may be inserted on validation failure');
    });

    TestSuite::run('rejects a duplicate email', function () use ($creator, &$createdEmails): void {
        $email = uniqueEmail('dup');
        $createdEmails[] = $email;
        $creator->create($email, 'Primero', VALID_PASSWORD);
        expectSeederException(fn() => $creator->create($email, 'Segundo', VALID_PASSWORD), 'Duplicate email must be rejected');
    });
} finally {
    if ($createdEmails !== []) {
        $placeholders = implode(',', array_fill(0, count($createdEmails), '?'));
        $pdo->prepare("DELETE FROM users WHERE email IN ({$placeholders})")->execute($createdEmails);
    }
}

TestSuite::summary();
exit(TestSuite::exitCode());
