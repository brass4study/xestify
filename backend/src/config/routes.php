<?php

declare(strict_types=1);

use Xestify\Controllers\HealthController;

$router->get('/health', [HealthController::class, 'index']);
