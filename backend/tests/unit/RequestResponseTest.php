<?php

declare(strict_types=1);

use Xestify\Core\Request;
use Xestify\Core\Response;

require_once dirname(__DIR__, 2) . '/src/Core/Request.php';
require_once dirname(__DIR__, 2) . '/src/Core/Response.php';

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

function assert_null(mixed $value, string $msg = 'Expected null'): void
{
    if ($value !== null) throw new RuntimeException($msg);
}

function assert_true(mixed $value, string $msg = 'Expected true'): void
{
    if ($value !== true) throw new RuntimeException($msg);
}

// Captura output de Response
function capture(callable $fn): array
{
    ob_start();
    $fn();
    $output = ob_get_clean();
    return json_decode($output, true) ?? [];
}

// ---------------------------------------------------------------------------
// Request tests
// ---------------------------------------------------------------------------

echo "\nRequestTest\n";
echo str_repeat('-', 40) . "\n";

test('query() devuelve valor de query params', function () {
    $req = new Request(query: ['page' => '2']);
    assert_equals('2', $req->query('page'));
});

test('query() devuelve default si key no existe', function () {
    $req = new Request();
    assert_equals('default', $req->query('missing', 'default'));
});

test('body() devuelve valor del body JSON', function () {
    $req = new Request(body: ['email' => 'a@b.com']);
    assert_equals('a@b.com', $req->body('email'));
});

test('body() devuelve default si key no existe', function () {
    $req = new Request();
    assert_null($req->body('nope'));
});

test('allBody() devuelve todo el body', function () {
    $data = ['name' => 'John', 'age' => 30];
    $req  = new Request(body: $data);
    assert_equals($data, $req->allBody());
});

test('header() es case-insensitive', function () {
    $req = new Request(headers: ['Content-Type' => 'application/json']);
    assert_equals('application/json', $req->header('content-type'));
    assert_equals('application/json', $req->header('Content-Type'));
});

test('bearerToken() extrae token del header Authorization', function () {
    $req = new Request(headers: ['Authorization' => 'Bearer my.jwt.token']);
    assert_equals('my.jwt.token', $req->bearerToken());
});

test('bearerToken() retorna null si no hay Authorization', function () {
    $req = new Request();
    assert_null($req->bearerToken());
});

test('bearerToken() retorna null si no es Bearer', function () {
    $req = new Request(headers: ['Authorization' => 'Basic dXNlcjpwYXNz']);
    assert_null($req->bearerToken());
});

test('param() devuelve route param', function () {
    $req = new Request(routeParams: ['slug' => 'client', 'id' => '42']);
    assert_equals('client', $req->param('slug'));
    assert_equals('42', $req->param('id'));
});

test('allParams() devuelve todos los route params', function () {
    $params = ['slug' => 'client'];
    $req    = new Request(routeParams: $params);
    assert_equals($params, $req->allParams());
});

// ---------------------------------------------------------------------------
// Response tests
// ---------------------------------------------------------------------------

echo "\nResponseTest\n";
echo str_repeat('-', 40) . "\n";

test('json() emite envelope ok:true con data', function () {
    $envelope = capture(fn() => Response::make()->json(['id' => 1]));
    assert_equals(true, $envelope['ok']);
    assert_equals(['id' => 1], $envelope['data']);
});

test('json() incluye meta si se pasa', function () {
    $envelope = capture(fn() => Response::make()->json([], ['total' => 100]));
    assert_equals(100, $envelope['meta']['total']);
});

test('json() omite meta si está vacío', function () {
    $envelope = capture(fn() => Response::make()->json([]));
    assert_true(!array_key_exists('meta', $envelope), 'meta no debe existir si está vacío');
});

test('error() emite envelope ok:false con code y message', function () {
    $envelope = capture(fn() => Response::make()->error(404, 'Not Found'));
    assert_equals(false, $envelope['ok']);
    assert_equals(404, $envelope['error']['code']);
    assert_equals('Not Found', $envelope['error']['message']);
});

test('error() incluye details si se pasa', function () {
    $details  = ['email' => ['El email es inválido']];
    $envelope = capture(fn() => Response::make()->error(422, 'Validation failed', $details));
    assert_equals($details, $envelope['error']['details']);
});

test('error() omite details si está vacío', function () {
    $envelope = capture(fn() => Response::make()->error(400, 'Bad Request'));
    assert_true(!array_key_exists('details', $envelope['error']), 'details no debe existir si está vacío');
});

test('unprocessable() shortcut emite 422', function () {
    $details  = ['name' => ['Requerido']];
    $envelope = capture(fn() => Response::make()->unprocessable('Validation failed', $details));
    assert_equals(false, $envelope['ok']);
    assert_equals(422, $envelope['error']['code']);
    assert_equals($details, $envelope['error']['details']);
});

test('unauthorized() shortcut emite 401', function () {
    $envelope = capture(fn() => Response::make()->unauthorized());
    assert_equals(401, $envelope['error']['code']);
});

test('notFound() shortcut emite 404', function () {
    $envelope = capture(fn() => Response::make()->notFound());
    assert_equals(404, $envelope['error']['code']);
});

// ---------------------------------------------------------------------------
// Resumen
// ---------------------------------------------------------------------------

echo str_repeat('-', 40) . "\n";
echo "Resultado: {$passed} passed, {$failed} failed\n\n";

exit($failed > 0 ? 1 : 0);
