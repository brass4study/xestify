<?php

declare(strict_types=1);

use Xestify\controllers\AuthController;
use Xestify\controllers\EntityController;
use Xestify\controllers\HealthController;
use Xestify\controllers\PluginExtensionController;

define('ROUTE_ENTITY_RECORD', '/api/v1/entities/{slug}/records/{id}');

$router->get('/health', [HealthController::class, 'index']);
$router->post('/api/v1/auth/login', [AuthController::class, 'login']);

// Entity list
$router->get('/api/v1/entities', [EntityController::class, 'listEntities']);

// Entity endpoints
$router->get('/api/v1/entities/{slug}/schema',        [EntityController::class, 'schema']);
$router->get('/api/v1/entities/{slug}/tabs',          [EntityController::class, 'tabs']);
$router->get('/api/v1/entities/{slug}/actions',       [EntityController::class, 'actions']);
$router->get('/api/v1/entities/{slug}/records',       [EntityController::class, 'index']);
$router->post('/api/v1/entities/{slug}/records',      [EntityController::class, 'create']);
$router->get(ROUTE_ENTITY_RECORD,    [EntityController::class, 'show']);
$router->put(ROUTE_ENTITY_RECORD,    [EntityController::class, 'update']);
$router->delete(ROUTE_ENTITY_RECORD, [EntityController::class, 'destroy']);

// Extension plugin endpoints (generic — plugin_slug discriminates between extension types)
define('ROUTE_PLUGIN_ITEM', '/api/v1/plugins/{plugin_slug}/{entity}/{id}/{item_id}');
$router->get('/api/v1/plugins/{plugin_slug}/{entity}/{id}',    [PluginExtensionController::class, 'index']);
$router->post('/api/v1/plugins/{plugin_slug}/{entity}/{id}',   [PluginExtensionController::class, 'create']);
$router->put(ROUTE_PLUGIN_ITEM,                                [PluginExtensionController::class, 'update']);
$router->delete(ROUTE_PLUGIN_ITEM,                             [PluginExtensionController::class, 'delete']);
