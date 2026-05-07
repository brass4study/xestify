<?php

declare(strict_types=1);

use Xestify\core\Request;
use Xestify\core\Response;

require_once __DIR__ . '/helpers.php';
require_once dirname(__DIR__, 2) . '/src/core/Request.php';
require_once dirname(__DIR__, 2) . '/src/core/Response.php';

const CONTENT_TYPE_JSON = 'application/json';

const MSG_VALIDATION_FAILED = 'Validation failed';

// Helper específico: captura y decodifica output de Response
function capture(callable $fn): array
{
    ob_start();
    $fn();
    $output = ob_get_clean() ?: '';
    return json_decode($output, true) ?? [];
}

// ---------------------------------------------------------------------------
// Request tests
// ---------------------------------------------------------------------------

echo "\nRequestTest\n";
echo str_repeat('-', 40) . "\n";

TestSuite::run('query() devuelve valor de query params', function () {
    $req = new Request(query: ['page' => '2']);
    assertEquals('2', $req->query('page'));
});

TestSuite::run('query() devuelve default si key no existe', function () {
    $req = new Request();
    assertEquals('default', $req->query('missing', 'default'));
});

TestSuite::run('body() devuelve valor del body JSON', function () {
    $req = new Request(body: ['email' => 'a@b.com']);
    assertEquals('a@b.com', $req->body('email'));
});

TestSuite::run('body() devuelve default si key no existe', function () {
    $req = new Request();
    assertNull($req->body('nope'));
});

TestSuite::run('allBody() devuelve todo el body', function () {
    $data = ['name' => 'John', 'age' => 30];
    $req  = new Request(body: $data);
    assertEquals($data, $req->allBody());
});

TestSuite::run('header() es case-insensitive', function () {
    $req = new Request(headers: ['Content-Type' => CONTENT_TYPE_JSON]);
    assertEquals(CONTENT_TYPE_JSON, $req->header('content-type'));
    assertEquals(CONTENT_TYPE_JSON, $req->header('Content-Type'));
});

TestSuite::run('bearerToken() extrae token del header Authorization', function () {
    $req = new Request(headers: ['Authorization' => 'Bearer my.jwt.token']);
    assertEquals('my.jwt.token', $req->bearerToken());
});

TestSuite::run('bearerToken() retorna null si no hay Authorization', function () {
    $req = new Request();
    assertNull($req->bearerToken());
});

TestSuite::run('bearerToken() retorna null si no es Bearer', function () {
    $req = new Request(headers: ['Authorization' => 'Basic dXNlcjpwYXNz']);
    assertNull($req->bearerToken());
});

TestSuite::run('param() devuelve route param', function () {
    $req = new Request(routeParams: ['slug' => 'client', 'id' => '42']);
    assertEquals('client', $req->param('slug'));
    assertEquals('42', $req->param('id'));
});

TestSuite::run('allParams() devuelve todos los route params', function () {
    $params = ['slug' => 'client'];
    $req    = new Request(routeParams: $params);
    assertEquals($params, $req->allParams());
});

TestSuite::run('uri() recorta el prefijo de alias antes de /api', function () {
    $previousUri = $_SERVER['REQUEST_URI'] ?? null;
    $_SERVER['REQUEST_URI'] = '/xestify/api/v1/auth/login';

    try {
        $req = new Request();
        assertEquals('/api/v1/auth/login', $req->uri());
    } finally {
        if ($previousUri === null) {
            unset($_SERVER['REQUEST_URI']);
        } else {
            $_SERVER['REQUEST_URI'] = $previousUri;
        }
    }
});

TestSuite::run('fromGlobals() recupera Authorization desde REDIRECT_HTTP_AUTHORIZATION', function () {
    $previousRedirectAuth = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? null;
    $previousHttpAuth = $_SERVER['HTTP_AUTHORIZATION'] ?? null;
    unset($_SERVER['HTTP_AUTHORIZATION']);
    $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] = 'Bearer redirected-token';

    try {
        $req = Request::fromGlobals();
        assertEquals('redirected-token', $req->bearerToken());
    } finally {
        if ($previousRedirectAuth === null) {
            unset($_SERVER['REDIRECT_HTTP_AUTHORIZATION']);
        } else {
            $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] = $previousRedirectAuth;
        }

        if ($previousHttpAuth === null) {
            unset($_SERVER['HTTP_AUTHORIZATION']);
        } else {
            $_SERVER['HTTP_AUTHORIZATION'] = $previousHttpAuth;
        }
    }
});

// ---------------------------------------------------------------------------
// Response tests
// ---------------------------------------------------------------------------

echo "\nResponseTest\n";
echo str_repeat('-', 40) . "\n";

TestSuite::run('json() emite envelope ok:true con data', function () {
    $envelope = capture(fn() => Response::make()->json(['id' => 1]));
    assertEquals(true, $envelope['ok']);
    assertEquals(['id' => 1], $envelope['data']);
});

TestSuite::run('json() incluye meta si se pasa', function () {
    $envelope = capture(fn() => Response::make()->json([], ['total' => 100]));
    assertEquals(100, $envelope['meta']['total']);
});

TestSuite::run('json() omite meta si está vacío', function () {
    $envelope = capture(fn() => Response::make()->json([]));
    assertTrue(!array_key_exists('meta', $envelope), 'meta no debe existir si está vacío');
});

TestSuite::run('error() emite envelope ok:false con code y message', function () {
    $envelope = capture(fn() => Response::make()->error(404, 'Not Found'));
    assertEquals(false, $envelope['ok']);
    assertEquals(404, $envelope['error']['code']);
    assertEquals('Not Found', $envelope['error']['message']);
});

TestSuite::run('error() incluye details si se pasa', function () {
    $details  = ['email' => ['El email es inválido']];
    $envelope = capture(fn() => Response::make()->error(422, MSG_VALIDATION_FAILED, $details));
    assertEquals($details, $envelope['error']['details']);
});

TestSuite::run('error() omite details si está vacío', function () {
    $envelope = capture(fn() => Response::make()->error(400, 'Bad Request'));
    assertTrue(!array_key_exists('details', $envelope['error']), 'details no debe existir si está vacío');
});

TestSuite::run('unprocessable() shortcut emite 422', function () {
    $details  = ['name' => ['Requerido']];
    $envelope = capture(fn() => Response::make()->unprocessable(MSG_VALIDATION_FAILED, $details));
    assertEquals(false, $envelope['ok']);
    assertEquals(422, $envelope['error']['code']);
    assertEquals($details, $envelope['error']['details']);
});

TestSuite::run('unauthorized() shortcut emite 401', function () {
    $envelope = capture(fn() => Response::make()->unauthorized());
    assertEquals(401, $envelope['error']['code']);
});

TestSuite::run('notFound() shortcut emite 404', function () {
    $envelope = capture(fn() => Response::make()->notFound());
    assertEquals(404, $envelope['error']['code']);
});

TestSuite::run('apiSuccess() emite envelope ok:true con data y meta', function () {
    $envelope = capture(fn() => Response::apiSuccess(['id' => 5], ['total' => 1]));
    assertEquals(true, $envelope['ok']);
    assertEquals(['id' => 5], $envelope['data']);
    assertEquals(1, $envelope['meta']['total']);
});

TestSuite::run('apiSuccess() omite meta cuando está vacío', function () {
    $envelope = capture(fn() => Response::apiSuccess(['x' => 1]));
    assertEquals(true, $envelope['ok']);
    assertTrue(!array_key_exists('meta', $envelope), 'meta no debe existir si está vacío');
});

TestSuite::run('apiError() emite envelope ok:false con code y message', function () {
    $envelope = capture(fn() => Response::apiError(400, 'Bad input'));
    assertEquals(false, $envelope['ok']);
    assertEquals(400, $envelope['error']['code']);
    assertEquals('Bad input', $envelope['error']['message']);
});

TestSuite::run('apiError() incluye details de validación por campo', function () {
    $details  = ['email' => ['Formato inválido'], 'name' => ['Requerido']];
    $envelope = capture(fn() => Response::apiError(422, MSG_VALIDATION_FAILED, $details));
    assertEquals(false, $envelope['ok']);
    assertEquals(422, $envelope['error']['code']);
    assertEquals($details, $envelope['error']['details']);
});

// ---------------------------------------------------------------------------
// Resumen
// ---------------------------------------------------------------------------

TestSuite::summary();
exit(TestSuite::exitCode());

