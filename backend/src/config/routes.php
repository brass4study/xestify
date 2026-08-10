<?php

declare(strict_types=1);

use Xestify\controllers\AuthController;
use Xestify\controllers\ConfigurationController;
use Xestify\controllers\EntityController;
use Xestify\controllers\HealthController;
use Xestify\controllers\PluginExtensionController;
use Xestify\controllers\PluginManagerController;
use Xestify\controllers\UserController;

if (!defined('ROUTE_ENTITY_RECORD')) {
    define('ROUTE_ENTITY_RECORD', '/api/v1/entities/{slug}/records/{id}');
}

if (!defined('ROUTE_USER_ITEM')) {
    define('ROUTE_USER_ITEM', '/api/v1/users/{id}');
}

$router->get('/health', [HealthController::class, 'index']);
$router->post('/api/v1/auth/login', [AuthController::class, 'login']);

// Global configuration endpoints
$router->get('/api/v1/configurations', [ConfigurationController::class, 'index']);
$router->get('/api/v1/configurations/{key}', [ConfigurationController::class, 'show']);
$router->put('/api/v1/configurations/{key}', [ConfigurationController::class, 'update']);

// User endpoints
$router->get('/api/v1/users/me', [UserController::class, 'me']);
$router->put('/api/v1/users/me', [UserController::class, 'updateMe']);
$router->get('/api/v1/users', [UserController::class, 'listUsers']);
$router->get(ROUTE_USER_ITEM, [UserController::class, 'show']);
$router->put(ROUTE_USER_ITEM, [UserController::class, 'update']);
$router->put('/api/v1/users/{id}/password', [UserController::class, 'resetPassword']);
$router->delete(ROUTE_USER_ITEM, [UserController::class, 'destroy']);

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
if (!defined('ROUTE_PLUGIN_ITEM')) {
    define('ROUTE_PLUGIN_ITEM', '/api/v1/plugins/{plugin_slug}/{entity}/{id}/{item_id}');
}
$router->get('/api/v1/plugins/{plugin_slug}/{entity}/{id}',    [PluginExtensionController::class, 'index']);
$router->post('/api/v1/plugins/{plugin_slug}/{entity}/{id}',   [PluginExtensionController::class, 'create']);
$router->put(ROUTE_PLUGIN_ITEM,                                [PluginExtensionController::class, 'update']);
$router->delete(ROUTE_PLUGIN_ITEM,                             [PluginExtensionController::class, 'delete']);

// Plugin manager endpoints
$router->get('/api/v1/plugins',                    [PluginManagerController::class, 'listPlugins']);
$router->post('/api/v1/plugins/sync',              [PluginManagerController::class, 'syncPlugins']);
$router->get('/api/v1/plugins/updates',            [PluginManagerController::class, 'listPluginUpdates']);
$router->post('/api/v1/plugins/{slug}/update',     [PluginManagerController::class, 'updatePlugin']);
$router->post('/api/v1/plugins/{slug}/rollback',   [PluginManagerController::class, 'rollbackPlugin']);
$router->put('/api/v1/plugins/{slug}/status',      [PluginManagerController::class, 'updatePluginStatus']);
$router->get('/api/v1/plugins/{slug}/config',      [PluginManagerController::class, 'getPluginConfig']);
$router->put('/api/v1/plugins/{slug}/config',      [PluginManagerController::class, 'updatePluginConfig']);
