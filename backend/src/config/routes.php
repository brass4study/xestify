<?php

declare(strict_types=1);

use Xestify\Controllers\AuthController;
use Xestify\Controllers\HealthController;

$router->get('/health', [HealthController::class, 'index']);
$router->post('/api/auth/login', [AuthController::class, 'login']);
