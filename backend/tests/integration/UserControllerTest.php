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
require_once BASE_PATH . '/src/services/ProfileSecretVerifier.php';
require_once BASE_PATH . '/src/services/ProfileUpdateAuthorizer.php';
require_once BASE_PATH . '/src/services/UserAuthorizer.php';
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

function insertUserRow(PDO $pdo, string $email, array $roles = ['operador'], string $password = 'secret', bool $isSeed = false): array
{
    $passwordHash = password_hash($password, PASSWORD_BCRYPT);
    $stmt = $pdo->prepare(
        'INSERT INTO users (email, password_hash, roles, name, avatar, is_seed)
         VALUES (:email, :password_hash, :roles, :name, :avatar, :is_seed)
         RETURNING id, email, password_hash, roles, name, avatar, is_seed, created_at'
    );
    $stmt->execute([
        ':email' => $email,
        ':password_hash' => $passwordHash,
        ':roles' => json_encode($roles),
        ':name' => 'Test User',
        ':avatar' => null,
        // PDO's implicit array-bind casts PHP `false` to '' (via (string) false),
        // which Postgres rejects for a boolean column — bind explicit string literals
        // instead of the raw bool.
        ':is_seed' => $isSeed ? 'true' : 'false',
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

TestSuite::run('UserController::update returns 409 when email is already in use by another user', function () use ($pdo, $controller): void {
    $admin = insertUserRow($pdo, 'dup-admin-' . uniqid('', true) . TEST_EMAIL_DOMAIN, ['admin']);
    $existing = insertUserRow($pdo, 'dup-existing-' . uniqid('', true) . TEST_EMAIL_DOMAIN);
    $target = insertUserRow($pdo, 'dup-target-' . uniqid('', true) . TEST_EMAIL_DOMAIN);

    try {
        $request = new Request([], ['email' => $existing['email']], [], []);
        $request->setUser(['sub' => (string) $admin['id'], 'roles' => ['admin']]);

        $response = captureControllerResponse(function () use ($controller, $target, $request): void {
            $controller->update(['id' => (string) $target['id']], $request);
        });

        assertFalse(($response['ok'] ?? true) === true, 'Duplicate email update should fail');
        assertEquals(409, (int) ($response['error']['code'] ?? 0), 'Duplicate email update should return 409, not an uncaught error');
    } finally {
        cleanupUserRow($pdo, (string) $admin['id']);
        cleanupUserRow($pdo, (string) $existing['id']);
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

TestSuite::run('UserController::destroy forbids deletion for a non-admin requester', function () use ($pdo, $controller): void {
    $target = insertUserRow($pdo, 'delete-nonadmin-target-' . uniqid('', true) . TEST_EMAIL_DOMAIN);
    $operator = insertUserRow($pdo, 'delete-operator-' . uniqid('', true) . TEST_EMAIL_DOMAIN, ['operador']);

    try {
        $request = new Request([], [], [], []);
        $request->setUser(['sub' => (string) $operator['id'], 'roles' => ['operador']]);

        $response = captureControllerResponse(fn() => $controller->destroy(['id' => (string) $target['id']], $request));
        assertFalse(($response['ok'] ?? true) === true, 'Non-admin deletion should fail');
        assertEquals(403, (int) ($response['error']['code'] ?? 0), 'Non-admin deletion should return 403');
    } finally {
        cleanupUserRow($pdo, (string) $operator['id']);
        cleanupUserRow($pdo, (string) $target['id']);
    }
});

TestSuite::run('UserController::destroy returns 404 when no id is provided', function () use ($pdo, $controller): void {
    $admin = insertUserRow($pdo, 'delete-admin-noid-' . uniqid('', true) . TEST_EMAIL_DOMAIN, ['admin']);

    try {
        $request = new Request([], [], [], []);
        $request->setUser(['sub' => (string) $admin['id'], 'roles' => ['admin']]);

        $response = captureControllerResponse(fn() => $controller->destroy([], $request));
        assertFalse(($response['ok'] ?? true) === true, 'Deletion without id should fail');
        assertEquals(404, (int) ($response['error']['code'] ?? 0), 'Deletion without id should return 404');
    } finally {
        cleanupUserRow($pdo, (string) $admin['id']);
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

TestSuite::run('UserController::update forbids editing a protected seed user', function () use ($pdo, $controller): void {
    $admin = insertUserRow($pdo, 'seed-update-admin-' . uniqid('', true) . TEST_EMAIL_DOMAIN, ['admin']);
    $seed = insertUserRow($pdo, 'seed-update-target-' . uniqid('', true) . TEST_EMAIL_DOMAIN, ['operador'], 'secret', true);

    try {
        $request = new Request([], ['name' => 'Renombrado'], [], []);
        $request->setUser(['sub' => (string) $admin['id'], 'roles' => ['admin']]);

        $response = captureControllerResponse(function () use ($controller, $seed, $request): void {
            $controller->update(['id' => (string) $seed['id']], $request);
        });

        assertFalse(($response['ok'] ?? true) === true, 'Editing a seed user should fail');
        assertEquals(403, (int) ($response['error']['code'] ?? 0), 'Editing a seed user should return 403');
    } finally {
        cleanupUserRow($pdo, (string) $admin['id']);
        cleanupUserRow($pdo, (string) $seed['id']);
    }
});

TestSuite::run('UserController::resetPassword forbids resetting a protected seed user', function () use ($pdo, $controller): void {
    $admin = insertUserRow($pdo, 'seed-reset-admin-' . uniqid('', true) . TEST_EMAIL_DOMAIN, ['admin']);
    $seed = insertUserRow($pdo, 'seed-reset-target-' . uniqid('', true) . TEST_EMAIL_DOMAIN, ['operador'], 'secret', true);

    try {
        $beforeHash = fetchUserPasswordHash($pdo, (string) $seed['id']);

        $request = new Request([], [], [], []);
        $request->setUser(['sub' => (string) $admin['id'], 'roles' => ['admin']]);

        $response = captureControllerResponse(function () use ($controller, $seed, $request): void {
            $controller->resetPassword(['id' => (string) $seed['id']], $request);
        });

        assertFalse(($response['ok'] ?? true) === true, 'Resetting a seed user password should fail');
        assertEquals(403, (int) ($response['error']['code'] ?? 0), 'Resetting a seed user password should return 403');
        assertEquals($beforeHash, fetchUserPasswordHash($pdo, (string) $seed['id']), 'The seed user password hash must be unchanged');
    } finally {
        cleanupUserRow($pdo, (string) $admin['id']);
        cleanupUserRow($pdo, (string) $seed['id']);
    }
});

TestSuite::run('UserController::destroy forbids deleting a protected seed user', function () use ($pdo, $repo, $controller): void {
    $admin = insertUserRow($pdo, 'seed-delete-admin-' . uniqid('', true) . TEST_EMAIL_DOMAIN, ['admin']);
    $seed = insertUserRow($pdo, 'seed-delete-target-' . uniqid('', true) . TEST_EMAIL_DOMAIN, ['operador'], 'secret', true);

    try {
        $request = new Request([], [], [], []);
        $request->setUser(['sub' => (string) $admin['id'], 'roles' => ['admin']]);

        $response = captureControllerResponse(fn() => $controller->destroy(['id' => (string) $seed['id']], $request));

        assertFalse(($response['ok'] ?? true) === true, 'Deleting a seed user should fail');
        assertEquals(403, (int) ($response['error']['code'] ?? 0), 'Deleting a seed user should return 403');
        assertTrue($repo->find((string) $seed['id']) !== null, 'The seed user must still exist');
    } finally {
        cleanupUserRow($pdo, (string) $admin['id']);
        cleanupUserRow($pdo, (string) $seed['id']);
    }
});

TestSuite::run('UserController::updateMe forbids a seed user from changing their own email', function () use ($pdo, $controller): void {
    $seed = insertUserRow($pdo, 'seed-self-email-' . uniqid('', true) . TEST_EMAIL_DOMAIN, ['admin'], 'secret', true);

    try {
        $request = new Request([], ['email' => 'new-seed-email@xestify.test', 'current_password' => 'secret'], [], []);
        $request->setUser(['sub' => (string) $seed['id'], 'roles' => ['admin']]);

        $response = captureControllerResponse(fn() => $controller->updateMe([], $request));

        assertFalse(($response['ok'] ?? true) === true, 'A seed user changing their own email should fail');
        assertEquals(403, (int) ($response['error']['code'] ?? 0), 'Seed self email change should return 403');
    } finally {
        cleanupUserRow($pdo, (string) $seed['id']);
    }
});

TestSuite::run('UserController::updateMe forbids a seed user from changing their own password', function () use ($pdo, $controller): void {
    $seed = insertUserRow($pdo, 'seed-self-password-' . uniqid('', true) . TEST_EMAIL_DOMAIN, ['admin'], 'secret', true);

    try {
        $beforeHash = fetchUserPasswordHash($pdo, (string) $seed['id']);

        $request = new Request([], ['password' => 'new-secret-123', 'current_password' => 'secret'], [], []);
        $request->setUser(['sub' => (string) $seed['id'], 'roles' => ['admin']]);

        $response = captureControllerResponse(fn() => $controller->updateMe([], $request));

        assertFalse(($response['ok'] ?? true) === true, 'A seed user changing their own password should fail');
        assertEquals(403, (int) ($response['error']['code'] ?? 0), 'Seed self password change should return 403');
        assertEquals($beforeHash, fetchUserPasswordHash($pdo, (string) $seed['id']), 'The seed user password hash must be unchanged');
    } finally {
        cleanupUserRow($pdo, (string) $seed['id']);
    }
});

TestSuite::run('UserController::updateMe allows a seed user to update their own name', function () use ($pdo, $controller): void {
    $seed = insertUserRow($pdo, 'seed-self-name-' . uniqid('', true) . TEST_EMAIL_DOMAIN, ['admin'], 'secret', true);

    try {
        $request = new Request([], ['name' => 'Nombre actualizado', 'email' => $seed['email']], [], []);
        $request->setUser(['sub' => (string) $seed['id'], 'roles' => ['admin']]);

        $response = captureControllerResponse(fn() => $controller->updateMe([], $request));

        assertTrue(($response['ok'] ?? false) === true, 'A seed user should still be able to rename themselves');
        assertEquals('Nombre actualizado', (string) ($response['data']['name'] ?? ''), 'The name should be updated');
    } finally {
        cleanupUserRow($pdo, (string) $seed['id']);
    }
});

TestSuite::summary();
exit(TestSuite::exitCode());
