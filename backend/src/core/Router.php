<?php

declare(strict_types=1);

namespace Xestify\core;

/**
 * Router HTTP minimalista.
 * Soporta rutas est├íticas y par├ímetros din├ímicos (:param).
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
     * Despacha la petici├│n actual al handler correspondiente.
     * Llamado desde bootstrap una vez registradas todas las rutas.
     */
    public function run(): void
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $uri    = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';

        $result = $this->dispatch($method, $uri);

        if ($result === null) {
            Response::make()->notFound();
        }
    }

    /**
     * Resuelve la ruta y ejecuta el handler. Devuelve true si encontr├│ ruta, null si no.
     *
     * @param string $method  M├®todo HTTP (GET, POST, ÔÇª)
     * @param string $uri     Path de la petici├│n
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
     * Convierte /entities/:slug/records/:id o /entities/{slug}/records/{id}
     * en un patrón con named groups.
     */
    private function buildPattern(string $path): string
    {
        $path    = '/' . trim($path, '/');
        $pattern = preg_replace(['/\{([a-zA-Z_]\w*)\}/', '/:([a-zA-Z_]\w*)/'], '(?P<$1>[^/]+)', $path) ?? $path;
        return '#^' . $pattern . '$#';
    }

    /**
     * Intenta hacer match. Devuelve array de par├ímetros capturados o null.
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
     *   - [ControllerClass::class, 'method']  ÔåÆ instancia via Container si est├í registrado, sino new
     *   - callable
     */
    private function callHandler(callable|array $handler, array $params): void
    {
        if (is_array($handler) && count($handler) === 2) {
            [$class, $method] = $handler;

            $instance = $this->container->has($class)
                ? $this->container->get($class)
                : new $class(); // NOSONAR S5992 ÔÇö clase siempre conocida en tiempo de registro de ruta

            $instance->$method($params); // NOSONAR S5992 ÔÇö m├®todo siempre conocido en tiempo de registro de ruta
            return;
        }

        ($handler)($params, $this->container);
    }
}
