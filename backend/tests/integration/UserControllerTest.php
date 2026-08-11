<?php

/**
 * UserControllerTest — Integration tests.
 *
 * Verifies the REST endpoints for the user profile and administration flows.
 * Requires a live PostgreSQL connection.
 */

declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__, 2));

const TEST_EMAIL_DOMAIN = '@xestify.test';

require_once BASE_PATH . '/tests/unit/helpers.php';
require_once BASE_PATH . '/src/exceptions/DatabaseException.php';
require_once BASE_PATH . '/src/exceptions/RepositoryException.php';
require_once BASE_PATH . '/src/core/Database.php';
require_once BASE_PATH . '/src/core/Request.php';
require_once BASE_PATH . '/src/core/Response.php';
require_once BASE_PATH . '/src/repositories/UserRepository.php';
require_once BASE_PATH . '/src/controllers/UserController.php';

use Xestify\core\Database;
use Xestify\core\Request;
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

function insertUserRow(PDO $pdo, string $email, array $roles = ['operador'], string $password = 'secret'): array
{
    $passwordHash = password_hash($password, PASSWORD_BCRYPT);
    $stmt = $pdo->prepare(
        'INSERT INTO users (email, password_hash, roles, name, avatar)
         VALUES (:email, :password_hash, :roles, :name, :avatar)
         RETURNING id, email, password_hash, roles, name, avatar, created_at'
    );
    $stmt->execute([
        ':email' => $email,
        ':password_hash' => $passwordHash,
        ':roles' => json_encode($roles),
        ':name' => 'Test User',
        ':avatar' => null,
    ]);

    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row === false) {
        throw new TestUserInsertException('Failed to insert test user');
    }

    return $row;
}

function cleanupUserRow(PDO $pdo, string $id): void
{
    $stmt = $pdo->prepare('DELETE FROM users WHERE id = :id');
    $stmt->execute([':id' => $id]);
}

function fetchUserPasswordHash(PDO $pdo, string $id): ?string
{
    $stmt = $pdo->prepare('SELECT password_hash FROM users WHERE id = :id');
    $stmt->execute([':id' => $id]);
    $value = $stmt->fetchColumn();
    return is_string($value) ? $value : null;
}

function captureControllerResponse(callable $callback): array
{
    ob_start();
    try {
        $callback();
    } finally {
        $content = ob_get_clean();
    }

    $decoded = json_decode((string) $content, true);
    return is_array($decoded) ? $decoded : [];
}

try {
    Database::connection();
} catch (DatabaseException) {
    echo "[SKIP] PostgreSQL not reachable — all UserControllerTest cases skipped.\n";
    echo "----------------------------------------\n";
    echo "Resultado: 0 passed, 0 failed (skipped)\n";
    exit(0);
}

$pdo = Database::connection();
$repo = new UserRepository($pdo);
$controller = new \Xestify\controllers\UserController($repo);

TestSuite::run('UserController::me returns the authenticated profile', function () use ($pdo, $controller): void {
    $user = insertUserRow($pdo, 'me-' . uniqid('', true) . TEST_EMAIL_DOMAIN);

    try {
        $request = new Request([], [], [], []);
        $request->setUser(['sub' => (string) $user['id'], 'roles' => ['operador']]);

        $response = captureControllerResponse(fn() => $controller->me([], $request));

        assertTrue(($response['ok'] ?? false) === true, 'Profile endpoint should succeed');
        assertEquals((string) $user['id'], (string) ($response['data']['id'] ?? ''), 'Returned profile should match the authenticated user');
        assertFalse(array_key_exists('password_hash', $response['data'] ?? []), 'password_hash must not be exposed in the JSON response');
    } finally {
        cleanupUserRow($pdo, (string) $user['id']);
    }
});

TestSuite::run('UserController::updateMe requires current password when changing email', function () use ($pdo, $controller): void {
    $user = insertUserRow($pdo, 'update-me-' . uniqid('', true) . TEST_EMAIL_DOMAIN);

    try {
        $request = new Request([], ['email' => 'new-email@xestify.test'], [], []);
        $request->setUser(['sub' => (string) $user['id'], 'roles' => ['operador']]);

        $response = captureControllerResponse(fn() => $controller->updateMe([], $request));

        assertFalse(($response['ok'] ?? true) === true, 'Changing email without current password should fail');
        assertEquals(422, (int) ($response['error']['code'] ?? 0), 'The endpoint should return 422 for missing current password');
    } finally {
        cleanupUserRow($pdo, (string) $user['id']);
    }
});

TestSuite::run('UserController::updateMe updates the password when current password is valid', function () use ($pdo, $controller): void {
    $user = insertUserRow($pdo, 'update-password-' . uniqid('', true) . TEST_EMAIL_DOMAIN, ['operador'], 'secret');

    try {
        $request = new Request([], ['password' => 'new-secret-123', 'current_password' => 'secret'], [], []);
        $request->setUser(['sub' => (string) $user['id'], 'roles' => ['operador']]);

        $response = captureControllerResponse(fn() => $controller->updateMe([], $request));

        assertTrue(($response['ok'] ?? false) === true, 'Password update should succeed');
        $meResponse = captureControllerResponse(function () use ($controller, $request): void {
            $controller->me([], $request);
        });
        assertTrue(($meResponse['ok'] ?? false) === true, 'Profile call completed after password update');
    } finally {
        cleanupUserRow($pdo, (string) $user['id']);
    }
});

TestSuite::run('UserController::updateMe allows name-only updates without current password when email is unchanged', function () use ($pdo, $controller): void {
    $user = insertUserRow($pdo, 'name-only-' . uniqid('', true) . TEST_EMAIL_DOMAIN);

    try {
        $request = new Request([], ['name' => 'Updated Name', 'email' => $user['email']], [], []);
        $request->setUser(['sub' => (string) $user['id'], 'roles' => ['operador']]);

        $response = captureControllerResponse(fn() => $controller->updateMe([], $request));

        assertTrue(($response['ok'] ?? false) === true, 'Name-only updates should succeed without current password');
        assertEquals('Updated Name', (string) ($response['data']['name'] ?? ''), 'The name should be updated');
    } finally {
        cleanupUserRow($pdo, (string) $user['id']);
    }
});

TestSuite::run('UserController::listUsers allows admins and returns active users', function () use ($pdo, $controller): void {
    $admin = insertUserRow($pdo, 'admin-list-' . uniqid('', true) . TEST_EMAIL_DOMAIN, ['admin']);
    $user = insertUserRow($pdo, 'user-list-' . uniqid('', true) . TEST_EMAIL_DOMAIN);

    try {
        $request = new Request([], [], [], []);
        $request->setUser(['sub' => (string) $admin['id'], 'roles' => ['admin']]);

        $response = captureControllerResponse(fn() => $controller->listUsers([], $request));

        assertTrue(($response['ok'] ?? false) === true, 'Admin listing should succeed');
        assertTrue(is_array($response['data'] ?? null), 'The response should include a users list');
        assertTrue(count($response['data']) >= 2, 'Admin listing should include the created users');
        foreach ($response['data'] as $listedUser) {
            assertFalse(array_key_exists('password_hash', $listedUser), 'password_hash must not be exposed for any listed user');
        }
    } finally {
        cleanupUserRow($pdo, (string) $admin['id']);
        cleanupUserRow($pdo, (string) $user['id']);
    }
});

TestSuite::run('UserController::update lets admins edit user roles', function () use ($pdo, $controller): void {
    $admin = insertUserRow($pdo, 'update-admin-' . uniqid('', true) . TEST_EMAIL_DOMAIN, ['admin']);
    $target = insertUserRow($pdo, 'update-target-' . uniqid('', true) . TEST_EMAIL_DOMAIN, ['operador']);

    try {
        $request = new Request([], ['name' => 'Gestor', 'roles' => ['admin', 'operador']], [], []);
        $request->setUser(['sub' => (string) $admin['id'], 'roles' => ['admin']]);

        $response = captureControllerResponse(function () use ($controller, $target, $request): void {
            $controller->update(['id' => (string) $target['id']], $request);
        });

        assertTrue(($response['ok'] ?? false) === true, 'Admin update should succeed');
        assertEquals('Gestor', (string) ($response['data']['name'] ?? ''), 'Updated name should be persisted');
        assertEquals(['admin', 'operador'], $response['data']['roles'] ?? [], 'Roles should be updated');
        assertFalse(array_key_exists('password_hash', $response['data'] ?? []), 'password_hash must not be exposed in the JSON response');
    } finally {
        cleanupUserRow($pdo, (string) $admin['id']);
        cleanupUserRow($pdo, (string) $target['id']);
    }
});

TestSuite::run('UserController::resetPassword allows admins and stores new hash', function () use ($pdo, $controller): void {
    $admin = insertUserRow($pdo, 'reset-admin-' . uniqid('', true) . TEST_EMAIL_DOMAIN, ['admin']);
    $target = insertUserRow($pdo, 'reset-target-' . uniqid('', true) . TEST_EMAIL_DOMAIN, ['operador'], 'initial-secret');

    try {
        $beforeHash = fetchUserPasswordHash($pdo, (string) $target['id']);
        assertTrue(is_string($beforeHash) && $beforeHash !== '', 'Initial hash should exist');

        $request = new Request([], [], [], []);
        $request->setUser(['sub' => (string) $admin['id'], 'roles' => ['admin']]);

        $response = captureControllerResponse(function () use ($controller, $target, $request): void {
            $controller->resetPassword(['id' => (string) $target['id']], $request);
        });

        assertTrue(($response['ok'] ?? false) === true, 'Admin password reset should succeed');
        $temporaryPassword = (string) ($response['data']['temporary_password'] ?? '');
        assertTrue($temporaryPassword !== '', 'Temporary password must be returned once');

        $afterHash = fetchUserPasswordHash($pdo, (string) $target['id']);
        assertTrue(is_string($afterHash) && $afterHash !== '', 'Updated hash should exist');
        assertTrue($beforeHash !== $afterHash, 'Password hash should change after reset');
        assertTrue(password_verify($temporaryPassword, $afterHash), 'Returned temporary password should match stored hash');
    } finally {
        cleanupUserRow($pdo, (string) $admin['id']);
        cleanupUserRow($pdo, (string) $target['id']);
    }
});

TestSuite::run('UserController::resetPassword forbids non-admin users', function () use ($pdo, $controller): void {
    $operator = insertUserRow($pdo, 'reset-operator-' . uniqid('', true) . TEST_EMAIL_DOMAIN, ['operador']);
    $target = insertUserRow($pdo, 'reset-non-admin-target-' . uniqid('', true) . TEST_EMAIL_DOMAIN, ['operador']);

    try {
        $request = new Request([], [], [], []);
        $request->setUser(['sub' => (string) $operator['id'], 'roles' => ['operador']]);

        $response = captureControllerResponse(function () use ($controller, $target, $request): void {
            $controller->resetPassword(['id' => (string) $target['id']], $request);
        });

        assertFalse(($response['ok'] ?? true) === true, 'Non-admin reset should fail');
        assertEquals(403, (int) ($response['error']['code'] ?? 0), 'Non-admin reset should return 403');
    } finally {
        cleanupUserRow($pdo, (string) $operator['id']);
        cleanupUserRow($pdo, (string) $target['id']);
    }
});

TestSuite::run('UserController::destroy soft deletes a user but blocks self delete', function () use ($pdo, $repo, $controller): void {
    $target = insertUserRow($pdo, 'delete-target-' . uniqid('', true) . TEST_EMAIL_DOMAIN);
    $admin = insertUserRow($pdo, 'delete-admin-' . uniqid('', true) . TEST_EMAIL_DOMAIN, ['admin']);

    try {
        $request = new Request([], [], [], []);
        $request->setUser(['sub' => (string) $admin['id'], 'roles' => ['admin']]);

        $response = captureControllerResponse(fn() => $controller->destroy(['id' => (string) $target['id']], $request));
        assertTrue(($response['ok'] ?? false) === true, 'Admin deletion should succeed');

        $deletedUser = $repo->find((string) $target['id']);
        assertNull($deletedUser, 'Deleted users should be excluded from repository reads');

        $selfDelete = captureControllerResponse(fn() => $controller->destroy(['id' => (string) $admin['id']], $request));
        assertFalse(($selfDelete['ok'] ?? true) === true, 'Admins should not delete themselves');
        assertEquals(422, (int) ($selfDelete['error']['code'] ?? 0), 'Self delete should return 422');
    } finally {
        cleanupUserRow($pdo, (string) $admin['id']);
        cleanupUserRow($pdo, (string) $target['id']);
    }
});

TestSuite::summary();
exit(TestSuite::exitCode());
