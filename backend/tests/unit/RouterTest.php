<?php

declare(strict_types=1);

use Xestify\core\Container;
use Xestify\core\Router;

require_once __DIR__ . '/helpers.php';
require_once dirname(__DIR__, 2) . '/src/core/Container.php';
require_once dirname(__DIR__, 2) . '/src/core/Router.php';

const ROUTE_HEALTH = '/health';
const ROUTE_ENTITY_1 = '/entities/1';

// ---------------------------------------------------------------------------
// Helpers específicos del Router
// ---------------------------------------------------------------------------

function makeRouter(): Router
{
    return new Router(new Container());
}

function dispatchCapture(Router $router, string $method, string $uri): array
{
    ob_start();
    $result = $router->dispatch($method, $uri);
    $output = ob_get_clean() ?: '';
    return [$result, $output];
}

// ---------------------------------------------------------------------------
// Tests
// ---------------------------------------------------------------------------

echo "\nRouterTest\n";
echo str_repeat('-', 40) . "\n";

TestSuite::run('GET ruta estática hace match y ejecuta handler', function () {
    $router = makeRouter();
    $called = false;

    $router->get(ROUTE_HEALTH, function () use (&$called) {
        $called = true;
    });

    [$result] = dispatchCapture($router, 'GET', ROUTE_HEALTH);
    assertTrue($result === true, 'dispatch debe retornar true');
    assertTrue($called, 'Handler no fue llamado');
});

TestSuite::run('POST ruta estática hace match', function () {
    $router = makeRouter();
    $called = false;

    $router->post('/auth/login', function () use (&$called) { $called = true; });

    [$result] = dispatchCapture($router, 'POST', '/auth/login');
    assertTrue($result === true);
    assertTrue($called);
});

TestSuite::run('PUT ruta estática hace match', function () {
    $router = makeRouter();
    $called = false;

    $router->put(ROUTE_ENTITY_1, function () use (&$called) { $called = true; });

    [$result] = dispatchCapture($router, 'PUT', ROUTE_ENTITY_1);
    assertTrue($result === true);
    assertTrue($called);
});

TestSuite::run('DELETE ruta estática hace match', function () {
    $router = makeRouter();
    $called = false;

    $router->delete(ROUTE_ENTITY_1, function () use (&$called) { $called = true; });

    [$result] = dispatchCapture($router, 'DELETE', ROUTE_ENTITY_1);
    assertTrue($result === true);
    assertTrue($called);
});

TestSuite::run('Ruta dinámica extrae un parámetro :slug', function () {
    $router = makeRouter();
    $captured = [];

    $router->get('/entities/:slug', function (array $params) use (&$captured) {
        $captured = $params;
    });

    dispatchCapture($router, 'GET', '/entities/client');
    assertEquals('client', $captured['slug'] ?? null);
});

TestSuite::run('Ruta dinámica extrae múltiples parámetros', function () {
    $router = makeRouter();
    $captured = [];

    $router->get('/entities/:slug/records/:id', function (array $params) use (&$captured) {
        $captured = $params;
    });

    dispatchCapture($router, 'GET', '/entities/client/records/42');
    assertEquals('client', $captured['slug'] ?? null);
    assertEquals('42', $captured['id'] ?? null);
});

TestSuite::run('Ruta no registrada retorna null', function () {
    $router = makeRouter();
    $router->get(ROUTE_HEALTH, fn() => null);

    [$result] = dispatchCapture($router, 'GET', '/nonexistent');
    assertNull($result, 'Ruta no registrada debería retornar null');
});

TestSuite::run('Método HTTP incorrecto no hace match', function () {
    $router = makeRouter();
    $router->get(ROUTE_HEALTH, fn() => null);

    [$result] = dispatchCapture($router, 'POST', ROUTE_HEALTH);
    assertNull($result, 'POST no debe hacer match con ruta GET');
});

TestSuite::run('Ruta con trailing slash es equivalente a sin slash', function () {
    $router = makeRouter();
    $called = false;

    $router->get(ROUTE_HEALTH, function () use (&$called) { $called = true; });

    dispatchCapture($router, 'GET', '/health/');
    assertTrue($called, 'Trailing slash no debe impedir el match');
});

TestSuite::run('Handler [Controller::class, method] se instancia y llama', function () {
    // Clase inline anónima como stand-in de controller
    $controllerClass = new class {
        public bool $wasCalled = false;
        public function handle(): void { $this->wasCalled = true; }
    };

    $container = new Container();
    $router    = new Router($container);

    // Registrar la instancia bajo su clase en el container
    $container->singleton(get_class($controllerClass), fn() => $controllerClass);

    $router->get('/test', [get_class($controllerClass), 'handle']);

    dispatchCapture($router, 'GET', '/test');
    assertTrue($controllerClass->wasCalled, 'Controller::handle no fue invocado');
});

// ---------------------------------------------------------------------------
// Resumen
// ---------------------------------------------------------------------------

TestSuite::summary();
exit(TestSuite::exitCode());

