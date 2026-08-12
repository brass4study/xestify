<?php

declare(strict_types=1);

namespace Xestify\core;

/**
 * Encapsula la peticion HTTP ya resuelta.
 * Contiene method, uri, headers, query params, body JSON y route params.
 */
class Request
{
    private array $query;
    private array $body;
    private array $headers;
    private array $routeParams;
    private string $method;
    private string $uri;
    private ?array $user = null;

    public function __construct(
        array $query = [],
        array $body = [],
        array $headers = [],
        array $routeParams = [],
        string $method = 'GET',
        string $uri = '/'
    ) {
        $this->query = $query;
        $this->body = $body;
        $this->headers = array_change_key_case($headers, CASE_LOWER);
        $this->routeParams = $routeParams;
        $this->method = strtoupper($method);
        $this->uri = $uri === '' ? '/' : $uri;
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
        $auth = (string) $this->header('authorization', '');
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
        return $this->method;
    }

    public function uri(): string
    {
        return $this->uri;
    }

    // -----------------------------------------------------------------------
    // Auth user (set by AuthMiddleware)
    // -----------------------------------------------------------------------

    public function setUser(array $payload): void
    {
        $this->user = $payload;
    }

    public function user(): ?array
    {
        return $this->user;
    }

    /**
     * True si el usuario autenticado tiene el rol dado (p. ej. 'admin').
     * Punto único de verdad para el check de autorización binaria admin/no-admin.
     */
    public function hasRole(string $role): bool
    {
        $roles = $this->user['roles'] ?? [];
        return is_array($roles) && in_array($role, $roles, true);
    }
}
