<?php

declare(strict_types=1);

namespace Xestify\Controllers;

class HealthController
{
    public function index(): void
    {
        http_response_code(200);
        header('Content-Type: application/json');
        echo json_encode([
            'ok'      => true,
            'version' => '0.1.0',
            'env'     => $_ENV['APP_ENV'] ?? 'unknown',
        ]);
    }
}
