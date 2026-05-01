<?php

declare(strict_types=1);

use Xestify\controllers\AuthController;
use Xestify\controllers\EntityController;
use Xestify\controllers\HealthController;

$router->get('/health', [HealthController::class, 'index']);
$router->post('/api/v1/auth/login', [AuthController::class, 'login']);

// Entity endpoints
$router->get('/api/v1/entities/{slug}/schema',        [EntityController::class, 'schema']);
$router->get('/api/v1/entities/{slug}/records',       [EntityController::class, 'index']);
$router->post('/api/v1/entities/{slug}/records',      [EntityController::class, 'create']);
$router->get('/api/v1/entities/{slug}/records/{id}',  [EntityController::class, 'show']);
$router->put('/api/v1/entities/{slug}/records/{id}',  [EntityController::class, 'update']);
$router->delete('/api/v1/entities/{slug}/records/{id}', [EntityController::class, 'destroy']);
