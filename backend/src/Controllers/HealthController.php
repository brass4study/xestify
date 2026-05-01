<?php

declare(strict_types=1);

namespace Xestify\controllers;

use Xestify\core\Response;

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
