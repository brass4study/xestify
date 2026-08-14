<?php

/**
 * AuthControllerTest — Integration tests for POST /api/v1/auth/login.
 *
 * Tests the AuthController directly (no HTTP server needed).
 * Requires a real PostgreSQL connection with the users table migrated.
 *
 * Run:
 *   php backend/tests/integration/AuthControllerTest.php
 */

declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__, 2));

const TEST_EMAIL_DOMAIN = '@xestify.test';

require_once BASE_PATH . '/tests/unit/helpers.php';
require_once BASE_PATH . '/src/exceptions/DatabaseException.php';
require_once BASE_PATH . '/src/exceptions/AuthException.php';
require_once BASE_PATH . '/src/exceptions/RepositoryException.php';
require_once BASE_PATH . '/src/core/AppDebug.php';
require_once BASE_PATH . '/src/core/Database.php';
require_once BASE_PATH . '/src/core/Request.php';
require_once BASE_PATH . '/src/core/Response.php';
require_once BASE_PATH . '/src/services/JwtService.php';
require_once BASE_PATH . '/src/database/seeders/UserSeeder.php';
require_once BASE_PATH . '/src/repositories/UserRepository.php';
require_once BASE_PATH . '/src/controllers/AuthController.php';

use Xestify\controllers\AuthController;
use Xestify\core\Database;
use Xestify\core\Request;
use Xestify\database\seeders\UserSeeder;
use Xestify\exceptions\DatabaseException;
use Xestify\repositories\UserRepository;
use Xestify\services\JwtService;

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
} catch (DatabaseException $e) {
    echo "[SKIP] PostgreSQL not reachable — AuthControllerTest skipped.\n";
    echo "       " . $e->getMessage() . "\n";
    exit(0);
}

// ---------------------------------------------------------------------------
// Setup: ensure admin user exists
// ---------------------------------------------------------------------------

UserSeeder::seedIfEmpty();

// ---------------------------------------------------------------------------
// Helper: capture Response output as decoded array
// ---------------------------------------------------------------------------

function callLogin(AuthController $controller, array $body): array
{
    $request = new Request([], $body, [], []);

    ob_start();
    $controller->login([], $request);
    $output = ob_get_clean();

    $decoded = json_decode((string) $output, true);
    return is_array($decoded) ? $decoded : [];
}

function deleteUserByEmail(\PDO $pdo, string $email): void
{
    $pdo->prepare('DELETE FROM users WHERE email = :email')->execute([':email' => $email]);
}

// ---------------------------------------------------------------------------
// Tests
// ---------------------------------------------------------------------------

$jwt        = new JwtService($_ENV['JWT_SECRET'] ?? 'changeme', 3600);
$controller = new AuthController($jwt, new UserRepository(Database::connection()));

define('ADMIN_EMAIL',        'admin@xestify.local');
define('ADMIN_PASSWORD',     'admin123');
define('MSG_OK_FALSE',       'ok should be false');
define('MSG_CODE_422',       'code should be 422');

TestSuite::run('login returns access_token for valid credentials', function () use ($controller): void {
    $result = callLogin($controller, ['email' => ADMIN_EMAIL, 'password' => ADMIN_PASSWORD]);

    assertTrue($result['ok'] ?? false, 'ok should be true');
    assertTrue(isset($result['data']['access_token']), 'access_token should be present');
    assertTrue(strlen($result['data']['access_token']) > 10, 'access_token should be a non-trivial string');
});

TestSuite::run('access_token is a valid decodable JWT', function () use ($controller, $jwt): void {
    $result = callLogin($controller, ['email' => ADMIN_EMAIL, 'password' => ADMIN_PASSWORD]);
    $token  = $result['data']['access_token'] ?? '';

    $payload = $jwt->decode($token);

    assertTrue(isset($payload['sub']), 'JWT payload should have sub');
    assertEquals(ADMIN_EMAIL, $payload['email'] ?? null, 'JWT email should match');
    assertTrue(in_array('admin', $payload['roles'] ?? [], true), 'JWT roles should contain admin');
});

TestSuite::run('login returns 401 for wrong password', function () use ($controller): void {
    $result = callLogin($controller, ['email' => ADMIN_EMAIL, 'password' => 'wrongpassword']);

    assertFalse($result['ok'] ?? true, MSG_OK_FALSE);
    assertEquals(401, $result['error']['code'] ?? null, 'code should be 401');
});

TestSuite::run('login returns 401 for unknown email', function () use ($controller): void {
    $result = callLogin($controller, ['email' => 'noexiste@xestify.local', 'password' => 'admin123']);

    assertFalse($result['ok'] ?? true, MSG_OK_FALSE);
    assertEquals(401, $result['error']['code'] ?? null, 'code should be 401');
});

TestSuite::run('login returns 422 when email is missing', function () use ($controller): void {
    $result = callLogin($controller, ['password' => 'admin123']);

    assertFalse($result['ok'] ?? true, MSG_OK_FALSE);
    assertEquals(422, $result['error']['code'] ?? null, MSG_CODE_422);
});

TestSuite::run('login returns 422 when password is missing', function () use ($controller): void {
    $result = callLogin($controller, ['email' => ADMIN_EMAIL]);

    assertFalse($result['ok'] ?? true, MSG_OK_FALSE);
    assertEquals(422, $result['error']['code'] ?? null, MSG_CODE_422);
});

TestSuite::run('login returns 422 when both fields are empty strings', function () use ($controller): void {
    $result = callLogin($controller, ['email' => '', 'password' => '']);

    assertFalse($result['ok'] ?? true, MSG_OK_FALSE);
    assertEquals(422, $result['error']['code'] ?? null, MSG_CODE_422);
    assertTrue(isset($result['error']['details']), 'details should be present');
});

TestSuite::run('login does not reveal whether email exists (same 401 for both cases)', function () use ($controller): void {
    $wrongPass    = callLogin($controller, ['email' => ADMIN_EMAIL, 'password' => 'bad']);
    $unknownEmail = callLogin($controller, ['email' => 'ghost@xestify.local', 'password' => 'bad']);

    assertEquals(
        $wrongPass['error']['message'] ?? null,
        $unknownEmail['error']['message'] ?? null,
        'Error message must be identical to prevent user enumeration'
    );
});

TestSuite::run('login rejects deleted users', function () use ($controller): void {
    $pdo = Database::connection();
    $email = 'deleted-' . uniqid('', true) . TEST_EMAIL_DOMAIN;
    $password = 'deleted-pass';
    $hash = password_hash($password, PASSWORD_BCRYPT);

    $stmt = $pdo->prepare(
        'INSERT INTO users (email, password_hash, roles, name, deleted_at)
         VALUES (:email, :password_hash, :roles, :name, NOW())'
    );
    $stmt->execute([
        ':email' => $email,
        ':password_hash' => $hash,
        ':roles' => '[]',
        ':name' => 'Deleted User',
    ]);

    try {
        $result = callLogin($controller, ['email' => $email, 'password' => $password]);

        assertFalse($result['ok'] ?? true, 'Deleted users should not be able to log in');
        assertEquals(401, $result['error']['code'] ?? null, 'Deleted users should receive 401');
    } finally {
        deleteUserByEmail($pdo, $email);
    }
});

// ---------------------------------------------------------------------------
// is_seed users (STORY 10.1 quick-access accounts) must be unusable outside
// of debug mode, since their credentials ship in plain text in the frontend
// bundle. Each test inserts its own throwaway seed user instead of reusing
// the real admin/usuario accounts, and always restores APP_DEBUG afterwards.
// ---------------------------------------------------------------------------

function insertThrowawaySeedUser(\PDO $pdo, string $email, string $password): void
{
    $stmt = $pdo->prepare(
        'INSERT INTO users (email, password_hash, roles, name, is_seed)
         VALUES (:email, :password_hash, :roles, :name, TRUE)'
    );
    $stmt->execute([
        ':email' => $email,
        ':password_hash' => password_hash($password, PASSWORD_BCRYPT),
        ':roles' => '[]',
        ':name' => 'Seed User',
    ]);
}

TestSuite::run('login rejects is_seed users when APP_DEBUG is false', function () use ($controller): void {
    $pdo = Database::connection();
    $email = 'seed-' . uniqid('', true) . TEST_EMAIL_DOMAIN;
    $password = 'seed-pass';
    insertThrowawaySeedUser($pdo, $email, $password);

    $originalDebug = $_ENV['APP_DEBUG'] ?? null;
    $_ENV['APP_DEBUG'] = 'false';

    try {
        $result = callLogin($controller, ['email' => $email, 'password' => $password]);

        assertFalse($result['ok'] ?? true, 'is_seed users should not be able to log in when APP_DEBUG=false');
        assertEquals(401, $result['error']['code'] ?? null, 'Blocked is_seed users should receive 401');
    } finally {
        if ($originalDebug === null) {
            unset($_ENV['APP_DEBUG']);
        } else {
            $_ENV['APP_DEBUG'] = $originalDebug;
        }
        deleteUserByEmail($pdo, $email);
    }
});

TestSuite::run('login allows is_seed users when APP_DEBUG is true', function () use ($controller): void {
    $pdo = Database::connection();
    $email = 'seed-' . uniqid('', true) . TEST_EMAIL_DOMAIN;
    $password = 'seed-pass';
    insertThrowawaySeedUser($pdo, $email, $password);

    $originalDebug = $_ENV['APP_DEBUG'] ?? null;
    $_ENV['APP_DEBUG'] = 'true';

    try {
        $result = callLogin($controller, ['email' => $email, 'password' => $password]);

        assertTrue($result['ok'] ?? false, 'is_seed users should be able to log in when APP_DEBUG=true');
        assertTrue(isset($result['data']['access_token']), 'access_token should be present when allowed');
    } finally {
        if ($originalDebug === null) {
            unset($_ENV['APP_DEBUG']);
        } else {
            $_ENV['APP_DEBUG'] = $originalDebug;
        }
        deleteUserByEmail($pdo, $email);
    }
});

TestSuite::run('a blocked is_seed user gets the same generic 401 as an unknown email', function () use ($controller): void {
    $pdo = Database::connection();
    $email = 'seed-' . uniqid('', true) . TEST_EMAIL_DOMAIN;
    $password = 'seed-pass';
    insertThrowawaySeedUser($pdo, $email, $password);

    $originalDebug = $_ENV['APP_DEBUG'] ?? null;
    $_ENV['APP_DEBUG'] = 'false';

    try {
        $blockedSeed = callLogin($controller, ['email' => $email, 'password' => $password]);
        $unknownEmail = callLogin($controller, ['email' => 'ghost-' . uniqid('', true) . TEST_EMAIL_DOMAIN, 'password' => 'bad']);

        assertEquals(
            $blockedSeed['error']['message'] ?? null,
            $unknownEmail['error']['message'] ?? null,
            'A blocked is_seed account must be indistinguishable from an unknown email'
        );
    } finally {
        if ($originalDebug === null) {
            unset($_ENV['APP_DEBUG']);
        } else {
            $_ENV['APP_DEBUG'] = $originalDebug;
        }
        deleteUserByEmail($pdo, $email);
    }
});

// ---------------------------------------------------------------------------

TestSuite::summary();
exit(TestSuite::exitCode());
