<?php

declare(strict_types=1);

use Xestify\controllers\AuthController;
use Xestify\controllers\ConfigurationController;
use Xestify\controllers\EntityController;
use Xestify\controllers\HealthController;
use Xestify\controllers\PluginExtensionController;
use Xestify\controllers\PluginManagerController;
use Xestify\controllers\UserController;
use Xestify\core\Request;

if (!defined('ROUTE_ENTITY_RECORD')) {
    define('ROUTE_ENTITY_RECORD', '/api/v1/entities/{slug}/records/{id}');
}

if (!defined('ROUTE_USER_ITEM')) {
    define('ROUTE_USER_ITEM', '/api/v1/users/{id}');
}

$router->get('/health', fn() => $container->get(HealthController::class)->index(), protected: false);
$router->post('/api/v1/auth/login', fn(array $params, Request $request) => $container->get(AuthController::class)->login($params, $request), protected: false);

// Global configuration endpoints
$router->get('/api/v1/configurations', fn(array $params, Request $request) => $container->get(ConfigurationController::class)->index($params, $request));
$router->get('/api/v1/configurations/{key}', fn(array $params, Request $request) => $container->get(ConfigurationController::class)->show($params, $request));
$router->put('/api/v1/configurations/{key}', fn(array $params, Request $request) => $container->get(ConfigurationController::class)->update($params, $request));

// User endpoints
$router->get('/api/v1/users/me', fn(array $params, Request $request) => $container->get(UserController::class)->me($params, $request));
$router->put('/api/v1/users/me', fn(array $params, Request $request) => $container->get(UserController::class)->updateMe($params, $request));
$router->get('/api/v1/users', fn(array $params, Request $request) => $container->get(UserController::class)->listUsers($params, $request));
$router->get(ROUTE_USER_ITEM, fn(array $params, Request $request) => $container->get(UserController::class)->show($params, $request));
$router->put(ROUTE_USER_ITEM, fn(array $params, Request $request) => $container->get(UserController::class)->update($params, $request));
$router->put('/api/v1/users/{id}/password', fn(array $params, Request $request) => $container->get(UserController::class)->resetPassword($params, $request));
$router->delete(ROUTE_USER_ITEM, fn(array $params, Request $request) => $container->get(UserController::class)->destroy($params, $request));

// Entity list
$router->get('/api/v1/entities', fn(array $params, Request $request) => $container->get(EntityController::class)->listEntities($params, $request));

// Entity endpoints
$router->get('/api/v1/entities/{slug}/schema', fn(array $params, Request $request) => $container->get(EntityController::class)->schema($params, $request));
$router->get('/api/v1/entities/{slug}/options', fn(array $params, Request $request) => $container->get(EntityController::class)->options($params, $request));
$router->get('/api/v1/entities/{slug}/tabs', fn(array $params, Request $request) => $container->get(EntityController::class)->tabs($params, $request));
$router->get('/api/v1/entities/{slug}/actions', fn(array $params, Request $request) => $container->get(EntityController::class)->actions($params, $request));
$router->get('/api/v1/entities/{slug}/records', fn(array $params, Request $request) => $container->get(EntityController::class)->index($params, $request));
$router->post('/api/v1/entities/{slug}/records', fn(array $params, Request $request) => $container->get(EntityController::class)->create($params, $request));
$router->get(ROUTE_ENTITY_RECORD, fn(array $params, Request $request) => $container->get(EntityController::class)->show($params, $request));
$router->put(ROUTE_ENTITY_RECORD, fn(array $params, Request $request) => $container->get(EntityController::class)->update($params, $request));
$router->delete(ROUTE_ENTITY_RECORD, fn(array $params, Request $request) => $container->get(EntityController::class)->destroy($params, $request));

// Extension plugin endpoints (generic — plugin_slug discriminates between extension types)
if (!defined('ROUTE_PLUGIN_ITEM')) {
    define('ROUTE_PLUGIN_ITEM', '/api/v1/plugins/{plugin_slug}/{entity}/{id}/{item_id}');
}
$router->get('/api/v1/plugins/{plugin_slug}/{entity}/{id}', fn(array $params, Request $request) => $container->get(PluginExtensionController::class)->index($params, $request));
$router->post('/api/v1/plugins/{plugin_slug}/{entity}/{id}', fn(array $params, Request $request) => $container->get(PluginExtensionController::class)->create($params, $request));
$router->put(ROUTE_PLUGIN_ITEM, fn(array $params, Request $request) => $container->get(PluginExtensionController::class)->update($params, $request));
$router->delete(ROUTE_PLUGIN_ITEM, fn(array $params, Request $request) => $container->get(PluginExtensionController::class)->delete($params, $request));

// Plugin manager endpoints
$router->get('/api/v1/plugins', fn(array $params, Request $request) => $container->get(PluginManagerController::class)->listPlugins($params, $request));
$router->get('/api/v1/plugins/available', fn(array $params, Request $request) => $container->get(PluginManagerController::class)->listAvailablePlugins($params, $request));
$router->post('/api/v1/plugins', fn(array $params, Request $request) => $container->get(PluginManagerController::class)->registerPlugin($params, $request));
$router->post('/api/v1/plugins/sync', fn(array $params, Request $request) => $container->get(PluginManagerController::class)->syncPlugins($params, $request));
$router->get('/api/v1/plugins/updates', fn(array $params, Request $request) => $container->get(PluginManagerController::class)->listPluginUpdates($params, $request));
$router->post('/api/v1/plugins/{slug}/update', fn(array $params, Request $request) => $container->get(PluginManagerController::class)->updatePlugin($params, $request));
$router->post('/api/v1/plugins/{slug}/rollback', fn(array $params, Request $request) => $container->get(PluginManagerController::class)->rollbackPlugin($params, $request));
$router->put('/api/v1/plugins/{slug}/status', fn(array $params, Request $request) => $container->get(PluginManagerController::class)->updatePluginStatus($params, $request));
$router->get('/api/v1/plugins/{slug}/config', fn(array $params, Request $request) => $container->get(PluginManagerController::class)->getPluginConfig($params, $request));
$router->put('/api/v1/plugins/{slug}/config', fn(array $params, Request $request) => $container->get(PluginManagerController::class)->updatePluginConfig($params, $request));
$router->delete('/api/v1/plugins/{slug}', fn(array $params, Request $request) => $container->get(PluginManagerController::class)->deletePlugin($params, $request));
