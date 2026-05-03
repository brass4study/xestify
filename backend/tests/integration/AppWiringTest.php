<?php

/**
 * AppWiringTest — verifies production-style container and router wiring.
 *
 * Covers integration risks that isolated unit tests cannot catch:
 * - protected API routes go through AuthMiddleware
 * - authenticated requests reach controllers
 * - EntityService uses the shared HookDispatcher registered at boot
 *
 * Run:
 *   php backend/tests/integration/AppWiringTest.php
 */

declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__, 2));

require_once BASE_PATH . '/tests/unit/helpers.php';

spl_autoload_register(function (string $class): void {
    $prefix = 'Xestify\\';
    $base = BASE_PATH . '/src/';

    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $relative = substr($class, strlen($prefix));
    $file = $base . str_replace('\\', '/', $relative) . '.php';

    if (file_exists($file)) {
        require_once $file;
    }
});

use Xestify\core\Container;
use Xestify\core\Database;
use Xestify\core\Router;
use Xestify\exceptions\DatabaseException;
use Xestify\exceptions\HookException;
use Xestify\plugins\PluginLoader;
use Xestify\services\EntityService;
use Xestify\services\JwtService;

$envFile = BASE_PATH . '/.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
    foreach ($lines as $line) {
        if (str_starts_with(trim($line), '#') || !str_contains($line, '=')) {
            continue;
        }
        [$key, $value] = explode('=', $line, 2);
        $_ENV[trim($key)] = trim($value);
        putenv(trim($key) . '=' . trim($value));
    }
}

try {
    Database::connection();
} catch (DatabaseException) {
    echo "[SKIP] PostgreSQL not reachable — all AppWiringTest cases skipped.\n";
    echo "       Configure backend/.env with valid DB_* vars and run migrations.\n";
    echo str_repeat('-', 40) . "\n";
    echo "Resultado: 0 passed, 0 failed (skipped)\n";
    exit(0);
}

function buildAppRouter(Container $container): Router
{
    require BASE_PATH . '/src/config/app.php';
    $router = new Router($container);
    require BASE_PATH . '/src/config/routes.php';
    return $router;
}

function dispatchApp(Router $router, string $method, string $uri, ?string $token = null): array
{
    $_SERVER['REQUEST_METHOD'] = $method;
    $_SERVER['REQUEST_URI'] = $uri;
    if ($token !== null) {
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $token;
    } else {
        unset($_SERVER['HTTP_AUTHORIZATION']);
    }

    ob_start();
    $router->dispatch($method, $uri);
    $output = ob_get_clean() ?: '';
    $decoded = json_decode($output, true);

    unset($_SERVER['HTTP_AUTHORIZATION']);

    return is_array($decoded) ? $decoded : [];
}

function issueToken(Container $container): string
{
    /** @var JwtService $jwt */
    $jwt = $container->get(JwtService::class);
    return $jwt->encode(['sub' => 'test-user', 'email' => 'test@example.com', 'roles' => ['admin']]);
}

echo str_repeat('-', 40) . "\n";

TestSuite::run('protected entity route returns 401 without token', function (): void {
    $container = new Container();
    $router = buildAppRouter($container);

    $result = dispatchApp($router, 'GET', '/api/v1/entities');

    assertFalse($result['ok'] ?? true, 'protected route must fail without token');
    assertEquals(401, $result['error']['code'] ?? null, 'protected route must return 401');
});

TestSuite::run('protected entity route accepts valid token', function (): void {
    $container = new Container();
    $router = buildAppRouter($container);
    $token = issueToken($container);

    $result = dispatchApp($router, 'GET', '/api/v1/entities', $token);

    assertTrue($result['ok'] ?? false, 'protected route must accept valid token');
    assertTrue(is_array($result['data'] ?? null), 'entities response data must be an array');
});

TestSuite::run('boot wiring injects active hooks into EntityService', function (): void {
    $container = new Container();
    buildAppRouter($container);

    /** @var PluginLoader $loader */
    $loader = $container->get(PluginLoader::class);
    $loader->activate('clients');

    $pdo = Database::connection();
    $email = 'duplicate-' . bin2hex(random_bytes(4)) . '@test.local';
    $pdo->prepare("DELETE FROM plugin_entity_data WHERE entity_slug = 'clients' AND content->>'email' = :email")
        ->execute([':email' => $email]);

    /** @var EntityService $service */
    $service = $container->get(EntityService::class);
    $service->createRecord('clients', ['name' => 'Ana Uno', 'email' => $email]);

    $threw = false;
    try {
        $service->createRecord('clients', ['name' => 'Ana Dos', 'email' => $email]);
    } catch (HookException) {
        $threw = true;
    } finally {
        $pdo->prepare("DELETE FROM plugin_entity_data WHERE entity_slug = 'clients' AND content->>'email' = :email")
            ->execute([':email' => $email]);
    }

    assertTrue($threw, 'duplicate email must be blocked by the clients beforeSave hook');
});

echo str_repeat('-', 40) . "\n";
TestSuite::summary();
exit(TestSuite::exitCode());
