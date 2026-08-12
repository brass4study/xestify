<?php

/**
 * UserRepositoryTest — Integration tests.
 *
 * Verifies the profile migration and the CRUD surface for UserRepository.
 * Requires a live PostgreSQL connection.
 */

declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__, 2));

const TEST_EMAIL_DOMAIN = '@xestify.test';

require_once BASE_PATH . '/tests/unit/helpers.php';
require_once BASE_PATH . '/src/exceptions/DatabaseException.php';
require_once BASE_PATH . '/src/exceptions/RepositoryException.php';
require_once BASE_PATH . '/src/core/Database.php';
require_once BASE_PATH . '/src/repositories/UserRepository.php';

use Xestify\core\Database;
use Xestify\exceptions\DatabaseException;
use Xestify\repositories\UserRepository;

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

class TestUserInsertException extends RuntimeException
{
}

try {
    Database::connection();
} catch (DatabaseException) {
    echo "[SKIP] PostgreSQL not reachable — all UserRepositoryTest cases skipped.\n";
    echo "       Configure backend/.env with valid DB_* vars and run the migrations in order (001-006).\n";
    echo "----------------------------------------\n";
    echo "Resultado: 0 passed, 0 failed (skipped)\n";
    exit(0);
}

function insertTestUser(PDO $pdo, string $email, ?string $avatar = null): array
{
    $passwordHash = password_hash('initial-pass', PASSWORD_BCRYPT);
    $stmt = $pdo->prepare(
        'INSERT INTO users (email, password_hash, roles, name, avatar)
         VALUES (:email, :password_hash, :roles, :name, :avatar)
         RETURNING id, email, password_hash, roles, name, avatar, created_at'
    );
    $stmt->execute([
        ':email' => $email,
        ':password_hash' => $passwordHash,
        ':roles' => '["operador"]',
        ':name' => 'Test User',
        ':avatar' => $avatar,
    ]);

    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row === false) {
        throw new TestUserInsertException('Failed to insert test user');
    }

    return $row;
}

function cleanupTestUser(PDO $pdo, string $id): void
{
    $stmt = $pdo->prepare('DELETE FROM users WHERE id = :id');
    $stmt->execute([':id' => $id]);
}

TestSuite::run('initial users migration defines profile columns', function (): void {
    $pdo = Database::connection();
    $migrationSql = file_get_contents(BASE_PATH . '/database/migrations/001_users.sql');
    assertTrue($migrationSql !== false, 'Initial users migration should be readable');

    $stmt = $pdo->query(
        "SELECT column_name
         FROM information_schema.columns
         WHERE table_schema = 'public' AND table_name = 'users'
           AND column_name IN ('name', 'avatar', 'deleted_at')
         ORDER BY column_name"
    );
    assertTrue($stmt !== false, 'Migration should expose table metadata');
    $columns = array_map(static fn(array $row): string => (string) $row['column_name'], $stmt->fetchAll(PDO::FETCH_ASSOC));
    assertEquals(['avatar', 'deleted_at', 'name'], $columns, 'Profile columns should be present in the initial users migration');
});

TestSuite::run('UserRepository::find and ::all return persisted profile data', function (): void {
    $pdo = Database::connection();
    $email = 'repo-find-' . uniqid('', true) . TEST_EMAIL_DOMAIN;
    $created = insertTestUser($pdo, $email);

    try {
        $repo = new UserRepository($pdo);
        $found = $repo->find((string) $created['id']);
        $all = $repo->all();

        assertTrue($found !== null, 'find() should return a user row');
        assertEquals($email, $found['email'], 'Email should match inserted value');
        assertEquals('Test User', $found['name'], 'Name should be persisted');
        assertTrue(is_array($found['roles']), 'find() must decode roles into a PHP array, not a raw JSON string');
        assertEquals(['operador'], $found['roles'], 'find() should decode the persisted roles JSON');
        assertTrue(count($all) >= 1, 'all() should return at least one user');
        $matching = array_values(array_filter($all, static fn(array $row): bool => $row['id'] === $found['id']));
        assertTrue($matching !== [], 'all() should include the inserted user');
        assertTrue(is_array($matching[0]['roles']), 'all() must decode roles into a PHP array, not a raw JSON string');
    } finally {
        cleanupTestUser($pdo, (string) $created['id']);
    }
});

TestSuite::run('UserRepository::update persists profile fields', function (): void {
    $pdo = Database::connection();
    $email = 'repo-update-' . uniqid('', true) . TEST_EMAIL_DOMAIN;
    $created = insertTestUser($pdo, $email);

    try {
        $avatar = "\x00\x01\x02\x03";
        $repo = new UserRepository($pdo);
        $updated = $repo->update((string) $created['id'], [
            'name' => 'Updated Name',
            'avatar' => $avatar,
            'roles' => ['admin', 'operador'],
        ]);

        assertEquals('Updated Name', $updated['name'], 'Name should be updated');
        assertTrue(is_array($updated['roles']), 'update() must return roles as a PHP array, not a raw JSON string');
        assertEquals(['admin', 'operador'], $updated['roles'], 'update() should return the newly persisted roles decoded');
        if (is_string($updated['avatar'])) {
            assertEquals($avatar, $updated['avatar'], 'Avatar binary data should be updated');
        } else {
            throw new AssertionError('Avatar value should be returned as a string');
        }
    } finally {
        cleanupTestUser($pdo, (string) $created['id']);
    }
});

TestSuite::run('UserRepository::updatePassword updates the password hash', function (): void {
    $pdo = Database::connection();
    $email = 'repo-password-' . uniqid('', true) . TEST_EMAIL_DOMAIN;
    $created = insertTestUser($pdo, $email);

    try {
        $repo = new UserRepository($pdo);
        $repo->updatePassword((string) $created['id'], 'new-hash');

        $fresh = $repo->find((string) $created['id']);
        assertEquals('new-hash', $fresh['password_hash'], 'Password hash should be updated');
    } finally {
        cleanupTestUser($pdo, (string) $created['id']);
    }
});

TestSuite::run('UserRepository::delete removes a user row', function (): void {
    $pdo = Database::connection();
    $email = 'repo-delete-' . uniqid('', true) . TEST_EMAIL_DOMAIN;
    $created = insertTestUser($pdo, $email);

    try {
        $repo = new UserRepository($pdo);
        $repo->delete((string) $created['id']);

        $fresh = $repo->find((string) $created['id']);
        assertNull($fresh, 'Deleted users should no longer be returned');
    } finally {
        cleanupTestUser($pdo, (string) $created['id']);
    }
});

TestSuite::summary();
exit(TestSuite::exitCode());
