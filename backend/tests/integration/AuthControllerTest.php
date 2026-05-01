<?php

/**
 * AuthControllerTest — Integration tests for POST /api/auth/login.
 *
 * Tests the AuthController directly (no HTTP server needed).
 * Requires a real PostgreSQL connection with the users table migrated.
 *
 * Run:
 *   php backend/tests/integration/AuthControllerTest.php
 */

declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__, 2));

require_once BASE_PATH . '/tests/unit/helpers.php';
require_once BASE_PATH . '/src/exceptions/DatabaseException.php';
require_once BASE_PATH . '/src/exceptions/AuthException.php';
require_once BASE_PATH . '/src/core/Database.php';
require_once BASE_PATH . '/src/core/Request.php';
require_once BASE_PATH . '/src/core/Response.php';
require_once BASE_PATH . '/src/services/JwtService.php';
require_once BASE_PATH . '/src/database/Seeders/UserSeeder.php';
require_once BASE_PATH . '/src/controllers/AuthController.php';

use Xestify\controllers\AuthController;
use Xestify\core\Database;
use Xestify\core\Request;
use Xestify\database\Seeders\UserSeeder;
use Xestify\exceptions\DatabaseException;
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

// ---------------------------------------------------------------------------
// Tests
// ---------------------------------------------------------------------------

$jwt        = new JwtService($_ENV['JWT_SECRET'] ?? 'changeme', 3600);
$controller = new AuthController($jwt);

TestSuite::run('login returns access_token for valid credentials', function () use ($controller): void {
    $result = callLogin($controller, ['email' => 'admin@xestify.local', 'password' => 'admin123']);

    assertTrue($result['ok'] ?? false, 'ok should be true');
    assertTrue(isset($result['data']['access_token']), 'access_token should be present');
    assertTrue(strlen($result['data']['access_token']) > 10, 'access_token should be a non-trivial string');
});

TestSuite::run('access_token is a valid decodable JWT', function () use ($controller, $jwt): void {
    $result = callLogin($controller, ['email' => 'admin@xestify.local', 'password' => 'admin123']);
    $token  = $result['data']['access_token'] ?? '';

    $payload = $jwt->decode($token);

    assertTrue(isset($payload['sub']), 'JWT payload should have sub');
    assertEquals('admin@xestify.local', $payload['email'] ?? null, 'JWT email should match');
    assertTrue(in_array('admin', $payload['roles'] ?? [], true), 'JWT roles should contain admin');
});

TestSuite::run('login returns 401 for wrong password', function () use ($controller): void {
    $result = callLogin($controller, ['email' => 'admin@xestify.local', 'password' => 'wrongpassword']);

    assertFalse($result['ok'] ?? true, 'ok should be false');
    assertEquals(401, $result['error']['code'] ?? null, 'code should be 401');
});

TestSuite::run('login returns 401 for unknown email', function () use ($controller): void {
    $result = callLogin($controller, ['email' => 'noexiste@xestify.local', 'password' => 'admin123']);

    assertFalse($result['ok'] ?? true, 'ok should be false');
    assertEquals(401, $result['error']['code'] ?? null, 'code should be 401');
});

TestSuite::run('login returns 422 when email is missing', function () use ($controller): void {
    $result = callLogin($controller, ['password' => 'admin123']);

    assertFalse($result['ok'] ?? true, 'ok should be false');
    assertEquals(422, $result['error']['code'] ?? null, 'code should be 422');
});

TestSuite::run('login returns 422 when password is missing', function () use ($controller): void {
    $result = callLogin($controller, ['email' => 'admin@xestify.local']);

    assertFalse($result['ok'] ?? true, 'ok should be false');
    assertEquals(422, $result['error']['code'] ?? null, 'code should be 422');
});

TestSuite::run('login returns 422 when both fields are empty strings', function () use ($controller): void {
    $result = callLogin($controller, ['email' => '', 'password' => '']);

    assertFalse($result['ok'] ?? true, 'ok should be false');
    assertEquals(422, $result['error']['code'] ?? null, 'code should be 422');
    assertTrue(isset($result['error']['details']), 'details should be present');
});

TestSuite::run('login does not reveal whether email exists (same 401 for both cases)', function () use ($controller): void {
    $wrongPass    = callLogin($controller, ['email' => 'admin@xestify.local', 'password' => 'bad']);
    $unknownEmail = callLogin($controller, ['email' => 'ghost@xestify.local', 'password' => 'bad']);

    assertEquals(
        $wrongPass['error']['message'] ?? null,
        $unknownEmail['error']['message'] ?? null,
        'Error message must be identical to prevent user enumeration'
    );
});

// ---------------------------------------------------------------------------

TestSuite::summary();
exit(TestSuite::exitCode());
