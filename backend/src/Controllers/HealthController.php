<?php

declare(strict_types=1);

namespace Xestify\Controllers;

use Xestify\Core\Response;

class HealthController
{
    public function index(): void
    {
        Response::make()->json([
            'version' => '0.1.0',
            'env'     => $_ENV['APP_ENV'] ?? 'unknown',
        ]);
    }
}
