<?php

declare(strict_types=1);

use Xestify\core\RequestFactory;
use Xestify\core\RuntimePathNormalizer;

require_once __DIR__ . '/helpers.php';
require_once dirname(__DIR__, 2) . '/src/core/Request.php';
require_once dirname(__DIR__, 2) . '/src/core/RequestFactory.php';
require_once dirname(__DIR__, 2) . '/src/core/RuntimePathNormalizer.php';

echo "\nRequestFactoryTest\n";
echo str_repeat('-', 40) . "\n";

TestSuite::run('create() construye Request con method y uri normalizados', function (): void {
    $factory = new RequestFactory(new RuntimePathNormalizer());
    $request = $factory->create(
        query: ['page' => '2'],
        body: ['name' => 'Ana'],
        headers: ['Authorization' => 'Bearer token-123'],
        routeParams: ['slug' => 'clients'],
        method: 'post',
        uri: '/xestify/api/v1/entities'
    );

    assertEquals('POST', $request->method());
    assertEquals('/api/v1/entities', $request->uri());
    assertEquals('2', $request->query('page'));
    assertEquals('Ana', $request->body('name'));
    assertEquals('token-123', $request->bearerToken());
    assertEquals('clients', $request->param('slug'));
});

TestSuite::run('fromGlobals() usa fallback Authorization y normaliza alias', function (): void {
    $factory = new RequestFactory(new RuntimePathNormalizer());
    $previousGet = $_GET;
    $previousRedirectAuth = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? null;
    $previousHttpAuth = $_SERVER['HTTP_AUTHORIZATION'] ?? null;
    $previousMethod = $_SERVER['REQUEST_METHOD'] ?? null;
    $previousUri = $_SERVER['REQUEST_URI'] ?? null;

    $_GET = ['page' => '3'];
    unset($_SERVER['HTTP_AUTHORIZATION']);
    $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] = 'Bearer redirected-factory-token';
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_SERVER['REQUEST_URI'] = '/xestify/api/v1/plugins';

    try {
        $request = $factory->fromGlobals(['slug' => 'comments']);

        assertEquals('GET', $request->method());
        assertEquals('/api/v1/plugins', $request->uri());
        assertEquals('3', $request->query('page'));
        assertEquals('redirected-factory-token', $request->bearerToken());
        assertEquals('comments', $request->param('slug'));
    } finally {
        $_GET = $previousGet;

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
    }
});

TestSuite::summary();
exit(TestSuite::exitCode());
