<?php

declare(strict_types=1);

namespace Xestify\core;

use Xestify\middleware\AuthMiddleware;

/**
 * Router HTTP minimalista.
 * Soporta rutas estaticas y parametros dinamicos (:param).
 * Los handlers son siempre callables explicitos (closures); la resolucion de
 * controladores vive en el propio registro de la ruta (routes.php), no aqui.
 */
class Router
{
    /** @var array<string, array<array{pattern: string, handler: callable, protected: bool}>> */
    private array $routes = [];

    public function __construct(
        private Container $container,
        private RequestFactory $requestFactory,
        private RuntimePathNormalizer $pathNormalizer
    ) {
    }

    /**
     * @param bool $protected Whether this route requires a valid bearer token. Defaults to
     *                        true (fail-closed): a route registered without an explicit
     *                        `protected: false` is protected, so a new route can never be
     *                        left unprotected by omission.
     */
    public function get(string $path, callable $handler, bool $protected = true): void
    {
        $this->addRoute('GET', $path, $handler, $protected);
    }

    public function post(string $path, callable $handler, bool $protected = true): void
    {
        $this->addRoute('POST', $path, $handler, $protected);
    }

    public function put(string $path, callable $handler, bool $protected = true): void
    {
        $this->addRoute('PUT', $path, $handler, $protected);
    }

    public function delete(string $path, callable $handler, bool $protected = true): void
    {
        $this->addRoute('DELETE', $path, $handler, $protected);
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
                $this->dispatchWithMiddleware($route['handler'], $params, $request, $route['protected']);

                return true;
            }
        }

        return null;
    }

    // -----------------------------------------------------------------------
    // Internals
    // -----------------------------------------------------------------------

    private function addRoute(string $method, string $path, callable $handler, bool $protected): void
    {
        $pattern = $this->buildPattern($path);
        $this->routes[$method][] = ['pattern' => $pattern, 'handler' => $handler, 'protected' => $protected];
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

    private function dispatchWithMiddleware(callable $handler, array $params, Request $request, bool $protected): void
    {
        if (!$protected) {
            $handler($params, $request);

            return;
        }

        if (!$this->container->has(AuthMiddleware::class)) {
            Response::make()->serverError('Auth middleware is not configured.');

            return;
        }

        /** @var AuthMiddleware $middleware */
        $middleware = $this->container->get(AuthMiddleware::class);
        $middleware->handle($request, function (Request $authenticatedRequest) use ($handler, $params): void {
            $handler($params, $authenticatedRequest);
        });
    }
}
