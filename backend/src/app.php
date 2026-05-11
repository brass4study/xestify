<?php

declare(strict_types=1);

use Xestify\core\Container;
use Xestify\core\RequestFactory;
use Xestify\core\Router;
use Xestify\core\RuntimePathNormalizer;

$container = new Container();

// Register core services
require_once __DIR__ . '/config/app.php';

$router = new Router(
    $container,
    $container->get(RequestFactory::class),
    $container->get(RuntimePathNormalizer::class)
);

// Register routes
require_once __DIR__ . '/config/routes.php';

return $router;
