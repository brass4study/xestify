<?php

declare(strict_types=1);

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/../../src/Exceptions/AuthException.php';
require_once __DIR__ . '/../../src/Services/JwtService.php';
require_once __DIR__ . '/../../src/Core/Request.php';
require_once __DIR__ . '/../../src/Core/Response.php';
require_once __DIR__ . '/../../src/Middleware/AuthMiddleware.php';

use Xestify\Core\Request;
use Xestify\Exceptions\AuthException;
use Xestify\Middleware\AuthMiddleware;
use Xestify\Services\JwtService;

const AUTH_SECRET = 'auth-middleware-test-secret';

// -----------------------------------------------------------------------
// Helpers
// -----------------------------------------------------------------------

function makeRequest(array $headers = [], array $routeParams = []): Request
{
    return new Request([], [], $headers, $routeParams);
}

// -----------------------------------------------------------------------
// No token
// -----------------------------------------------------------------------

TestSuite::run('handle calls next when token is valid', function (): void {
    $jwt        = new JwtService(AUTH_SECRET, 3600);
    $middleware = new AuthMiddleware($jwt);
    $token      = $jwt->encode(['sub' => '1', 'email' => 'a@x.com']);
    $request    = makeRequest(['authorization' => "Bearer {$token}"]);

    $called  = false;
    $received = null;
    $middleware->handle($request, function (Request $req) use (&$called, &$received): void {
        $called   = true;
        $received = $req;
    });

    assertTrue($called, 'next() should have been called');
    assertNull(null, ''); // dummy — we confirm via $called
    $user = $received?->user();
    assertTrue($user !== null, 'user should be set on request');
    assertEquals('1', $user['sub'] ?? null, 'sub mismatch');
});

TestSuite::run('handle sets user payload on request', function (): void {
    $jwt        = new JwtService(AUTH_SECRET, 3600);
    $middleware = new AuthMiddleware($jwt);
    $token      = $jwt->encode(['sub' => '42', 'roles' => ['admin']]);
    $request    = makeRequest(['authorization' => "Bearer {$token}"]);

    $middleware->handle($request, function (Request $req): void {
        assertEquals('42', $req->user()['sub'] ?? null, 'sub should be 42');
        assertEquals(['admin'], $req->user()['roles'] ?? null, 'roles should match');
    });
});

TestSuite::run('handle does not call next when no token', function (): void {
    $jwt        = new JwtService(AUTH_SECRET, 3600);
    $middleware = new AuthMiddleware($jwt);
    $request    = makeRequest([]);

    $called = false;
    $middleware->handle($request, function () use (&$called): void {
        $called = true;
    });

    assertFalse($called, 'next() should NOT be called without token');
});

// -----------------------------------------------------------------------
// Bad / expired token
// -----------------------------------------------------------------------

TestSuite::run('handle does not call next on expired token', function (): void {
    $jwt        = new JwtService(AUTH_SECRET, -1);  // expired immediately
    $middleware = new AuthMiddleware($jwt);
    $token      = $jwt->encode(['sub' => '1']);
    $request    = makeRequest(['authorization' => "Bearer {$token}"]);

    $called = false;
    $middleware->handle($request, function () use (&$called): void {
        $called = true;
    });

    assertFalse($called, 'next() should NOT be called for expired token');
});

TestSuite::run('handle does not call next on invalid signature', function (): void {
    $jwtSign    = new JwtService(AUTH_SECRET, 3600);
    $jwtVerify  = new AuthMiddleware(new JwtService('different-secret', 3600));
    $token      = $jwtSign->encode(['sub' => '1']);
    $request    = makeRequest(['authorization' => "Bearer {$token}"]);

    $called = false;
    $jwtVerify->handle($request, function () use (&$called): void {
        $called = true;
    });

    assertFalse($called, 'next() should NOT be called for wrong-signature token');
});

TestSuite::run('handle does not call next on malformed token', function (): void {
    $jwt        = new JwtService(AUTH_SECRET, 3600);
    $middleware = new AuthMiddleware($jwt);
    $request    = makeRequest(['authorization' => 'Bearer notavalidtoken']);

    $called = false;
    $middleware->handle($request, function () use (&$called): void {
        $called = true;
    });

    assertFalse($called, 'next() should NOT be called for malformed token');
});

// -----------------------------------------------------------------------

TestSuite::summary();
exit(TestSuite::exitCode());
