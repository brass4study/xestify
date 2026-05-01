<?php

declare(strict_types=1);

namespace Xestify\Core;

use InvalidArgumentException;

/**
 * Router HTTP minimalista.
 * Soporta rutas estáticas y parámetros dinámicos (:param).
 * Mapea rutas a [Controller::class, 'method'] o cualquier callable.
 */
class Router
{
    /** @var array<string, array<array{pattern: string, handler: callable|array}>> */
    private array $routes = [];

    private Container $container;

    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    public function get(string $path, callable|array $handler): void
    {
        $this->addRoute('GET', $path, $handler);
    }

    public function post(string $path, callable|array $handler): void
    {
        $this->addRoute('POST', $path, $handler);
    }

    public function put(string $path, callable|array $handler): void
    {
        $this->addRoute('PUT', $path, $handler);
    }

    public function delete(string $path, callable|array $handler): void
    {
        $this->addRoute('DELETE', $path, $handler);
    }

    /**
     * Despacha la petición actual al handler correspondiente.
     * Llamado desde bootstrap una vez registradas todas las rutas.
     */
    public function run(): void
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $uri    = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);

        $result = $this->dispatch($method, $uri);

        if ($result === null) {
            http_response_code(404);
            header('Content-Type: application/json');
            echo json_encode(['ok' => false, 'error' => ['code' => 404, 'message' => 'Not Found']]);
        }
    }

    /**
     * Resuelve la ruta y ejecuta el handler. Devuelve true si encontró ruta, null si no.
     *
     * @param string $method  Método HTTP (GET, POST, …)
     * @param string $uri     Path de la petición
     * @return true|null
     */
    public function dispatch(string $method, string $uri): ?true
    {
        $method = strtoupper($method);
        $uri    = '/' . trim($uri, '/');

        foreach ($this->routes[$method] ?? [] as $route) {
            $params = $this->matchRoute($route['pattern'], $uri);
            if ($params !== null) {
                $this->callHandler($route['handler'], $params);
                return true;
            }
        }

        return null;
    }

    // -----------------------------------------------------------------------
    // Internals
    // -----------------------------------------------------------------------

    private function addRoute(string $method, string $path, callable|array $handler): void
    {
        $pattern = $this->buildPattern($path);
        $this->routes[$method][] = ['pattern' => $pattern, 'handler' => $handler];
    }

    /**
     * Convierte /entities/:slug/records/:id en un patrón con named groups.
     */
    private function buildPattern(string $path): string
    {
        $path    = '/' . trim($path, '/');
        $pattern = preg_replace('/:([a-zA-Z_][a-zA-Z0-9_]*)/', '(?P<$1>[^/]+)', $path);
        return '#^' . $pattern . '$#';
    }

    /**
     * Intenta hacer match. Devuelve array de parámetros capturados o null.
     *
     * @return array<string, string>|null
     */
    private function matchRoute(string $pattern, string $uri): ?array
    {
        if (!preg_match($pattern, $uri, $matches)) {
            return null;
        }

        // Filtra solo los named captures (string keys)
        return array_filter(
            $matches,
            fn($key) => is_string($key),
            ARRAY_FILTER_USE_KEY
        );
    }

    /**
     * Ejecuta el handler. Soporta:
     *   - [ControllerClass::class, 'method']  → instancia via Container si está registrado, sino new
     *   - callable
     */
    private function callHandler(callable|array $handler, array $params): void
    {
        if (is_array($handler) && count($handler) === 2) {
            [$class, $method] = $handler;

            $instance = $this->container->has($class)
                ? $this->container->get($class)
                : new $class();

            $instance->$method($params);
            return;
        }

        ($handler)($params, $this->container);
    }
}
