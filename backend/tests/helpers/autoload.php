<?php

declare(strict_types=1);

spl_autoload_register(function (string $class): void {
    $prefix = 'Xestify\\';
    $base = BASE_PATH . '/src/';

    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $relative = substr($class, strlen($prefix));
    $file = $base . str_replace('\\', '/', $relative) . '.php';

    if (file_exists($file)) {
        require_once $file;
    }
});
