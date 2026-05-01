<?php

declare(strict_types=1);

namespace Xestify\Core;

/**
 * Encapsula la petición HTTP entrante.
 * Lee headers, query params, body JSON y route params.
 */
class Request
{
    private array $query;
    private array $body;
    private array $headers;
    private array $routeParams;

    public function __construct(
        array $query       = [],
        array $body        = [],
        array $headers     = [],
        array $routeParams = []
    ) {
        $this->query       = $query;
        $this->body        = $body;
        $this->headers     = array_change_key_case($headers, CASE_LOWER);
        $this->routeParams = $routeParams;
    }

    /**
     * Construye un Request desde las superglobales PHP ($_GET, php://input, $_SERVER).
     */
    public static function fromGlobals(array $routeParams = []): self
    {
        $body = [];
        $raw  = file_get_contents('php://input');
        if ($raw !== '' && $raw !== false) {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $body = $decoded;
            }
        }

        $headers = [];
        foreach ($_SERVER as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $name           = strtolower(str_replace('_', '-', substr($key, 5)));
                $headers[$name] = $value;
            }
        }

        return new self($_GET, $body, $headers, $routeParams);
    }

    // -----------------------------------------------------------------------
    // Query params
    // -----------------------------------------------------------------------

    public function query(string $key, mixed $default = null): mixed
    {
        return $this->query[$key] ?? $default;
    }

    public function allQuery(): array
    {
        return $this->query;
    }

    // -----------------------------------------------------------------------
    // Body (JSON)
    // -----------------------------------------------------------------------

    public function body(string $key, mixed $default = null): mixed
    {
        return $this->body[$key] ?? $default;
    }

    public function allBody(): array
    {
        return $this->body;
    }

    // -----------------------------------------------------------------------
    // Headers
    // -----------------------------------------------------------------------

    public function header(string $name, mixed $default = null): mixed
    {
        return $this->headers[strtolower($name)] ?? $default;
    }

    public function bearerToken(): ?string
    {
        $auth = $this->header('authorization', '');
        if (str_starts_with($auth, 'Bearer ')) {
            return substr($auth, 7);
        }
        return null;
    }

    // -----------------------------------------------------------------------
    // Route params
    // -----------------------------------------------------------------------

    public function param(string $key, mixed $default = null): mixed
    {
        return $this->routeParams[$key] ?? $default;
    }

    public function allParams(): array
    {
        return $this->routeParams;
    }

    // -----------------------------------------------------------------------
    // Method & URI
    // -----------------------------------------------------------------------

    public function method(): string
    {
        return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    }

    public function uri(): string
    {
        return parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
    }
}
