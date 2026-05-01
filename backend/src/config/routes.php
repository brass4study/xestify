<?php

declare(strict_types=1);

use Xestify\controllers\AuthController;
use Xestify\controllers\HealthController;

$router->get('/health', [HealthController::class, 'index']);
$router->post('/api/auth/login', [AuthController::class, 'login']);
