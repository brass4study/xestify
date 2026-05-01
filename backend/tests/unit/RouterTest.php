<?php

declare(strict_types=1);

use Xestify\Core\Container;
use Xestify\Core\Router;

require_once dirname(__DIR__, 2) . '/src/Core/Container.php';
require_once dirname(__DIR__, 2) . '/src/Core/Router.php';

// ---------------------------------------------------------------------------
// Utilidad de test (reutiliza la misma convención que ContainerTest)
// ---------------------------------------------------------------------------

$passed = 0;
$failed = 0;

function test(string $label, callable $fn): void
{
    global $passed, $failed;
    try {
        $fn();
        echo "  ✅ {$label}\n";
        $passed++;
    } catch (Throwable $e) {
        echo "  ❌ {$label}\n     → {$e->getMessage()}\n";
        $failed++;
    }
}

function assert_equals(mixed $expected, mixed $actual, string $msg = ''): void
{
    if ($expected !== $actual) {
        throw new RuntimeException(
            $msg ?: "Expected " . var_export($expected, true) . ", got " . var_export($actual, true)
        );
    }
}

function assert_true(mixed $value, string $msg = 'Expected true'): void
{
    if ($value !== true) throw new RuntimeException($msg);
}

function assert_null(mixed $value, string $msg = 'Expected null'): void
{
    if ($value !== null) throw new RuntimeException($msg);
}

// Helper: crea router fresco con container vacío
function makeRouter(): Router
{
    return new Router(new Container());
}

// Helper: captura la salida de dispatch
function dispatchCapture(Router $router, string $method, string $uri): array
{
    ob_start();
    $result = $router->dispatch($method, $uri);
    $output = ob_get_clean();
    return [$result, $output];
}

// ---------------------------------------------------------------------------
// Tests
// ---------------------------------------------------------------------------

echo "\nRouterTest\n";
echo str_repeat('-', 40) . "\n";

test('GET ruta estática hace match y ejecuta handler', function () {
    $router = makeRouter();
    $called = false;

    $router->get('/health', function (array $params) use (&$called) {
        $called = true;
    });

    [$result] = dispatchCapture($router, 'GET', '/health');
    assert_true($result === true, 'dispatch debe retornar true');
    assert_true($called, 'Handler no fue llamado');
});

test('POST ruta estática hace match', function () {
    $router = makeRouter();
    $called = false;

    $router->post('/auth/login', function () use (&$called) { $called = true; });

    [$result] = dispatchCapture($router, 'POST', '/auth/login');
    assert_true($result === true);
    assert_true($called);
});

test('PUT ruta estática hace match', function () {
    $router = makeRouter();
    $called = false;

    $router->put('/entities/1', function () use (&$called) { $called = true; });

    [$result] = dispatchCapture($router, 'PUT', '/entities/1');
    assert_true($result === true);
    assert_true($called);
});

test('DELETE ruta estática hace match', function () {
    $router = makeRouter();
    $called = false;

    $router->delete('/entities/1', function () use (&$called) { $called = true; });

    [$result] = dispatchCapture($router, 'DELETE', '/entities/1');
    assert_true($result === true);
    assert_true($called);
});

test('Ruta dinámica extrae un parámetro :slug', function () {
    $router = makeRouter();
    $captured = [];

    $router->get('/entities/:slug', function (array $params) use (&$captured) {
        $captured = $params;
    });

    dispatchCapture($router, 'GET', '/entities/client');
    assert_equals('client', $captured['slug'] ?? null);
});

test('Ruta dinámica extrae múltiples parámetros', function () {
    $router = makeRouter();
    $captured = [];

    $router->get('/entities/:slug/records/:id', function (array $params) use (&$captured) {
        $captured = $params;
    });

    dispatchCapture($router, 'GET', '/entities/client/records/42');
    assert_equals('client', $captured['slug'] ?? null);
    assert_equals('42', $captured['id'] ?? null);
});

test('Ruta no registrada retorna null', function () {
    $router = makeRouter();
    $router->get('/health', function () {});

    [$result] = dispatchCapture($router, 'GET', '/nonexistent');
    assert_null($result, 'Ruta no registrada debería retornar null');
});

test('Método HTTP incorrecto no hace match', function () {
    $router = makeRouter();
    $router->get('/health', function () {});

    [$result] = dispatchCapture($router, 'POST', '/health');
    assert_null($result, 'POST no debe hacer match con ruta GET');
});

test('Ruta con trailing slash es equivalente a sin slash', function () {
    $router = makeRouter();
    $called = false;

    $router->get('/health', function () use (&$called) { $called = true; });

    dispatchCapture($router, 'GET', '/health/');
    assert_true($called, 'Trailing slash no debe impedir el match');
});

test('Handler [Controller::class, method] se instancia y llama', function () {
    // Clase inline anónima como stand-in de controller
    $controllerClass = new class {
        public bool $wasCalled = false;
        public function handle(array $params): void { $this->wasCalled = true; }
    };

    $container = new Container();
    $router    = new Router($container);

    // Registrar la instancia bajo su clase en el container
    $container->singleton(get_class($controllerClass), fn() => $controllerClass);

    $router->get('/test', [get_class($controllerClass), 'handle']);

    dispatchCapture($router, 'GET', '/test');
    assert_true($controllerClass->wasCalled, 'Controller::handle no fue invocado');
});

// ---------------------------------------------------------------------------
// Resumen
// ---------------------------------------------------------------------------

echo str_repeat('-', 40) . "\n";
echo "Resultado: {$passed} passed, {$failed} failed\n\n";

exit($failed > 0 ? 1 : 0);
