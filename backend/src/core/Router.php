<?php

declare(strict_types=1);

namespace Xestify\core;

use ReflectionMethod;
use Xestify\middleware\AuthMiddleware;

/**
 * Router HTTP minimalista.
 * Soporta rutas estaticas y parametros dinamicos (:param).
 * Mapea rutas a [Controller::class, 'method'] o cualquier callable.
 */
class Router
{
    /** @var array<string, array<array{pattern: string, handler: callable|array}>> */
    private array $routes = [];

    /** @var string[] */
    private array $protectedPrefixes = ['/api/v1/entities', '/api/v1/plugins'];

    public function __construct(
        private Container $container,
        private RequestFactory $requestFactory,
        private RuntimePathNormalizer $pathNormalizer
    ) {
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
     * Despacha la peticion actual al handler correspondiente.
     * Llamado desde bootstrap una vez registradas todas las rutas.
     */
    public function run(): void
    {
        $method = (string) ($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $uri = (string) (parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/');

        $result = $this->dispatch($method, $uri);

        if ($result === null) {
            Response::make()->notFound();
        }
    }

    /**
     * Resuelve la ruta y ejecuta el handler. Devuelve true si encontro ruta, null si no.
     *
     * @param string $method HTTP method (GET, POST, etc.)
     * @param string $uri    Path de la peticion
     * @return true|null
     */
    public function dispatch(string $method, string $uri): ?true
    {
        $normalizedMethod = strtoupper($method);
        $normalizedUri = '/' . trim($this->pathNormalizer->normalize($uri), '/');

        foreach ($this->routes[$normalizedMethod] ?? [] as $route) {
            $params = $this->matchRoute($route['pattern'], $normalizedUri);
            if ($params !== null) {
                $request = $this->requestFactory->fromGlobals($params, $normalizedMethod, $normalizedUri);
                $this->dispatchWithMiddleware($route['handler'], $params, $request, $normalizedUri);

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
     * en un patron con named groups.
     */
    private function buildPattern(string $path): string
    {
        $path = '/' . trim($path, '/');
        $pattern = preg_replace(['/\{([a-zA-Z_]\w*)\}/', '/:([a-zA-Z_]\w*)/'], '(?P<$1>[^/]+)', $path) ?? $path;

        return '#^' . $pattern . '$#';
    }

    /**
     * Intenta hacer match. Devuelve array de parametros capturados o null.
     *
     * @return array<string, string>|null
     */
    private function matchRoute(string $pattern, string $uri): ?array
    {
        if (!preg_match($pattern, $uri, $matches)) {
            return null;
        }

        return array_filter(
            $matches,
            fn($key) => is_string($key),
            ARRAY_FILTER_USE_KEY
        );
    }

    /**
     * Ejecuta el handler. Soporta:
     *   - [ControllerClass::class, 'method'] instancia via Container si esta registrado, sino new
     *   - callable
     */
    private function dispatchWithMiddleware(callable|array $handler, array $params, Request $request, string $uri): void
    {
        if (!$this->requiresAuth($uri)) {
            $this->callHandler($handler, $params, $request);

            return;
        }

        if (!$this->container->has(AuthMiddleware::class)) {
            Response::make()->serverError('Auth middleware is not configured.');

            return;
        }

        /** @var AuthMiddleware $middleware */
        $middleware = $this->container->get(AuthMiddleware::class);
        $middleware->handle($request, function (Request $authenticatedRequest) use ($handler, $params): void {
            $this->callHandler($handler, $params, $authenticatedRequest);
        });
    }

    private function requiresAuth(string $uri): bool
    {
        foreach ($this->protectedPrefixes as $prefix) {
            if ($uri === $prefix || str_starts_with($uri, $prefix . '/')) {
                return true;
            }
        }

        return false;
    }

    private function callHandler(callable|array $handler, array $params, ?Request $request = null): void
    {
        if (is_array($handler) && count($handler) === 2) {
            [$target, $method] = $handler;

            if (is_object($target)) {
                $this->invokeController($target, (string) $method, $params, $request);

                return;
            }

            $instance = $this->container->has($target)
                ? $this->container->get($target)
                : new $target(); // NOSONAR S5992 - clase conocida al registrar la ruta

            $this->invokeController($instance, (string) $method, $params, $request);

            return;
        }

        ($handler)($params, $this->container);
    }

    private function invokeController(object $instance, string $method, array $params, ?Request $request): void
    {
        $ref = new ReflectionMethod($instance, $method);

        if ($request !== null && $ref->getNumberOfParameters() >= 2) {
            $instance->$method($params, $request); // NOSONAR S5992 - metodo conocido al registrar la ruta

            return;
        }

        $instance->$method($params); // NOSONAR S5992 - metodo conocido al registrar la ruta
    }
}
