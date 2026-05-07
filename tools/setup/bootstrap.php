<?php

declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__, 2) . '/backend');

$envFile = BASE_PATH . '/.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
    foreach ($lines as $line) {
        if (str_starts_with(trim($line), '#') || !str_contains($line, '=')) {
            continue;
        }

        [$key, $value] = explode('=', $line, 2);
        $_ENV[trim($key)] = trim($value);
        putenv(trim($key) . '=' . trim($value));
    }
}

spl_autoload_register(function (string $class): void {
    $prefix = 'Xestify\\';
    $base   = BASE_PATH . '/src/';

    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $relative = substr($class, strlen($prefix));
    $file = $base . str_replace('\\', '/', $relative) . '.php';

    if (file_exists($file)) {
        require_once $file;
    }
});
