<?php

declare(strict_types=1);

use Xestify\controllers\HealthController;

require_once __DIR__ . '/helpers.php';
require_once dirname(__DIR__, 2) . '/src/core/AppDebug.php';
require_once dirname(__DIR__, 2) . '/src/core/Response.php';
require_once dirname(__DIR__, 2) . '/src/controllers/HealthController.php';

function captureHealthResponse(): array
{
    ob_start();
    (new HealthController())->index();
    $output = ob_get_clean() ?: '';
    return json_decode($output, true) ?? [];
}

// ---------------------------------------------------------------------------

$originalAppDebug = $_ENV['APP_DEBUG'] ?? null;

TestSuite::run('HealthController::index exposes debug=true when APP_DEBUG=true', function (): void {
    $_ENV['APP_DEBUG'] = 'true';
    $response = captureHealthResponse();
    assertTrue(($response['data']['debug'] ?? null) === true, 'debug should be true when APP_DEBUG=true');
});

TestSuite::run('HealthController::index exposes debug=false when APP_DEBUG=false', function (): void {
    $_ENV['APP_DEBUG'] = 'false';
    $response = captureHealthResponse();
    assertTrue(($response['data']['debug'] ?? null) === false, 'debug should be false when APP_DEBUG=false');
});

TestSuite::run('HealthController::index defaults debug to false when APP_DEBUG is not set', function (): void {
    unset($_ENV['APP_DEBUG']);
    $response = captureHealthResponse();
    assertTrue(($response['data']['debug'] ?? null) === false, 'debug should default to false when APP_DEBUG is unset (fail-closed)');
});

if ($originalAppDebug === null) {
    unset($_ENV['APP_DEBUG']);
} else {
    $_ENV['APP_DEBUG'] = $originalAppDebug;
}

// ---------------------------------------------------------------------------

TestSuite::summary();
exit(TestSuite::exitCode());
