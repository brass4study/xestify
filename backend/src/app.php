<?php

declare(strict_types=1);

use Xestify\Core\Container;
use Xestify\Core\Router;

$container = new Container();

// Register core services
require_once __DIR__ . '/config/app.php';

$router = new Router($container);

// Register routes
require_once __DIR__ . '/config/routes.php';

return $router;
