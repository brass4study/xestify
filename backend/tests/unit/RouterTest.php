<?php

declare(strict_types=1);

use Xestify\core\Container;
use Xestify\core\RequestFactory;
use Xestify\core\Router;
use Xestify\core\RuntimePathNormalizer;

require_once __DIR__ . '/helpers.php';
require_once dirname(__DIR__, 2) . '/src/core/Container.php';
require_once dirname(__DIR__, 2) . '/src/core/Request.php';
require_once dirname(__DIR__, 2) . '/src/core/RequestFactory.php';
require_once dirname(__DIR__, 2) . '/src/core/Response.php';
require_once dirname(__DIR__, 2) . '/src/core/Router.php';
require_once dirname(__DIR__, 2) . '/src/core/RuntimePathNormalizer.php';
require_once dirname(__DIR__, 2) . '/src/exceptions/AuthException.php';
require_once dirname(__DIR__, 2) . '/src/services/JwtService.php';
require_once dirname(__DIR__, 2) . '/src/middleware/AuthMiddleware.php';

use Xestify\core\Request;
use Xestify\middleware\AuthMiddleware;
use Xestify\services\JwtService;

const ROUTE_HEALTH = '/health';
const ROUTE_ENTITY_1 = '/entities/1';
const ROUTE_API_ENTITIES = '/api/v1/entities';
const MSG_DISPATCH_RETURNS_TRUE = 'dispatch debe retornar true';

// ---------------------------------------------------------------------------
// Helpers específicos del Router
// ---------------------------------------------------------------------------

function makeRouter(): Router
{
    $container = new Container();
    $normalizer = new RuntimePathNormalizer();

    return new Router($container, new RequestFactory($normalizer), $normalizer);
}

function dispatchCapture(Router $router, string $method, string $uri): array
{
    $previousMethod = $_SERVER['REQUEST_METHOD'] ?? null;
    $previousUri = $_SERVER['REQUEST_URI'] ?? null;
    $_SERVER['REQUEST_METHOD'] = $method;
    $_SERVER['REQUEST_URI'] = $uri;

    ob_start();
    $result = $router->dispatch($method, $uri);
    $output = ob_get_clean() ?: '';

    if ($previousMethod === null) {
        unset($_SERVER['REQUEST_METHOD']);
    } else {
        $_SERVER['REQUEST_METHOD'] = $previousMethod;
    }

    if ($previousUri === null) {
        unset($_SERVER['REQUEST_URI']);
    } else {
        $_SERVER['REQUEST_URI'] = $previousUri;
    }

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
    }, protected: false);

    [$result] = dispatchCapture($router, 'GET', ROUTE_HEALTH);
    assertTrue($result === true, MSG_DISPATCH_RETURNS_TRUE);
    assertTrue($called, 'Handler no fue llamado');
});

TestSuite::run('POST ruta estática hace match', function () {
    $router = makeRouter();
    $called = false;

    $router->post('/auth/login', function () use (&$called) { $called = true; }, protected: false);

    [$result] = dispatchCapture($router, 'POST', '/auth/login');
    assertTrue($result === true);
    assertTrue($called);
});

TestSuite::run('PUT ruta estática hace match', function () {
    $router = makeRouter();
    $called = false;

    $router->put(ROUTE_ENTITY_1, function () use (&$called) { $called = true; }, protected: false);

    [$result] = dispatchCapture($router, 'PUT', ROUTE_ENTITY_1);
    assertTrue($result === true);
    assertTrue($called);
});

TestSuite::run('DELETE ruta estática hace match', function () {
    $router = makeRouter();
    $called = false;

    $router->delete(ROUTE_ENTITY_1, function () use (&$called) { $called = true; }, protected: false);

    [$result] = dispatchCapture($router, 'DELETE', ROUTE_ENTITY_1);
    assertTrue($result === true);
    assertTrue($called);
});

TestSuite::run('Ruta dinámica extrae un parámetro :slug', function () {
    $router = makeRouter();
    $captured = [];

    $router->get('/entities/:slug', function (array $params) use (&$captured) {
        $captured = $params;
    }, protected: false);

    dispatchCapture($router, 'GET', '/entities/widgets');
    assertEquals('widgets', $captured['slug'] ?? null);
});

TestSuite::run('Ruta dinámica extrae múltiples parámetros', function () {
    $router = makeRouter();
    $captured = [];

    $router->get('/entities/:slug/records/:id', function (array $params) use (&$captured) {
        $captured = $params;
    }, protected: false);

    dispatchCapture($router, 'GET', '/entities/widgets/records/42');
    assertEquals('widgets', $captured['slug'] ?? null);
    assertEquals('42', $captured['id'] ?? null);
});

TestSuite::run('Ruta no registrada retorna null', function () {
    $router = makeRouter();
    $router->get(ROUTE_HEALTH, fn() => null, protected: false);

    [$result] = dispatchCapture($router, 'GET', '/nonexistent');
    assertNull($result, 'Ruta no registrada debería retornar null');
});

TestSuite::run('Método HTTP incorrecto no hace match', function () {
    $router = makeRouter();
    $router->get(ROUTE_HEALTH, fn() => null, protected: false);

    [$result] = dispatchCapture($router, 'POST', ROUTE_HEALTH);
    assertNull($result, 'POST no debe hacer match con ruta GET');
});

TestSuite::run('Ruta con trailing slash es equivalente a sin slash', function () {
    $router = makeRouter();
    $called = false;

    $router->get(ROUTE_HEALTH, function () use (&$called) { $called = true; }, protected: false);

    dispatchCapture($router, 'GET', '/health/');
    assertTrue($called, 'Trailing slash no debe impedir el match');
});

TestSuite::run('Ruta bajo alias /xestify hace match con endpoints API', function () {
    $router = makeRouter();
    $called = false;

    $router->post('/api/v1/auth/login', function () use (&$called) {
        $called = true;
    }, protected: false);

    [$result] = dispatchCapture($router, 'POST', '/xestify/api/v1/auth/login');
    assertTrue($result === true, 'dispatch debe retornar true bajo alias');
    assertTrue($called, 'La ruta bajo alias debe resolver al endpoint API');
});

TestSuite::run('Handler closure resuelve el controller desde el container y lo invoca', function () {
    // Clase inline anónima como stand-in de controller
    $controllerClass = new class {
        public bool $wasCalled = false;
        public function handle(): void { $this->wasCalled = true; }
    };

    $container = new Container();
    $normalizer = new RuntimePathNormalizer();
    $router = new Router($container, new RequestFactory($normalizer), $normalizer);

    // Registrar la instancia bajo su clase en el container
    $container->singleton(get_class($controllerClass), fn() => $controllerClass);

    $router->get('/test', fn() => $container->get(get_class($controllerClass))->handle(), protected: false);

    dispatchCapture($router, 'GET', '/test');
    assertTrue($controllerClass->wasCalled, 'Controller::handle no fue invocado');
});

TestSuite::run('Ruta protegida requiere token bearer', function () {
    $container = new Container();
    $container->singleton(AuthMiddleware::class, fn() => new AuthMiddleware(new JwtService('router-secret')));
    $normalizer = new RuntimePathNormalizer();
    $router = new Router($container, new RequestFactory($normalizer), $normalizer);
    $called = false;

    $router->get(ROUTE_API_ENTITIES, function () use (&$called) {
        $called = true;
    });

    [$result, $output] = dispatchCapture($router, 'GET', ROUTE_API_ENTITIES);
    $decoded = json_decode($output, true);

    assertTrue($result === true, MSG_DISPATCH_RETURNS_TRUE);
    assertFalse($called, 'handler protegido no debe ejecutarse sin token');
    assertEquals(401, $decoded['error']['code'] ?? null, 'debe devolver 401');
});

TestSuite::run('Ruta protegida entrega Request autenticada al controller', function () {
    $jwt = new JwtService('router-secret');
    $token = $jwt->encode(['sub' => 'user-1', 'email' => 'admin@test.local']);
    $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $token;

    $controller = new class {
        public ?array $user = null;
        public function index(array $params, Request $request): void
        {
            assertEquals([], $params, 'Ruta sin parametros debe entregar array vacio');
            $this->user = $request->user();
        }
    };

    $container = new Container();
    $container->singleton(AuthMiddleware::class, fn() => new AuthMiddleware($jwt));
    $container->singleton(get_class($controller), fn() => $controller);
    $normalizer = new RuntimePathNormalizer();
    $router = new Router($container, new RequestFactory($normalizer), $normalizer);
    $router->get(ROUTE_API_ENTITIES, fn(array $params, Request $request) => $container->get(get_class($controller))->index($params, $request));

    try {
        dispatchCapture($router, 'GET', ROUTE_API_ENTITIES);
        assertEquals('user-1', $controller->user['sub'] ?? null, 'Request::user debe llegar al controller');
    } finally {
        unset($_SERVER['HTTP_AUTHORIZATION']);
    }
});

TestSuite::run('Una ruta nueva sin declarar "protected" explicitamente queda protegida por defecto', function () {
    $container = new Container();
    $container->singleton(AuthMiddleware::class, fn() => new AuthMiddleware(new JwtService('router-secret')));
    $normalizer = new RuntimePathNormalizer();
    $router = new Router($container, new RequestFactory($normalizer), $normalizer);
    $called = false;

    // Ruta arbitraria que nunca habria estado en la antigua lista de
    // prefijos hardcodeada ($protectedPrefixes) - debe quedar protegida
    // igualmente, sin que nadie tenga que acordarse de añadirla a ningun sitio.
    $router->get('/api/v1/a-brand-new-endpoint-nobody-remembered-to-protect', function () use (&$called) {
        $called = true;
    });

    [$result, $output] = dispatchCapture($router, 'GET', '/api/v1/a-brand-new-endpoint-nobody-remembered-to-protect');
    $decoded = json_decode($output, true);

    assertTrue($result === true, MSG_DISPATCH_RETURNS_TRUE);
    assertFalse($called, 'una ruta nueva sin protected:false explicito no debe ejecutarse sin token');
    assertEquals(401, $decoded['error']['code'] ?? null, 'debe devolver 401 por defecto, sin necesidad de listar el prefijo en ningun sitio');
});

// ---------------------------------------------------------------------------
// Resumen
// ---------------------------------------------------------------------------

TestSuite::summary();
exit(TestSuite::exitCode());

