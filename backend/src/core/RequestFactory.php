<?php

declare(strict_types=1);

namespace Xestify\core;

class RequestFactory
{
    private const AUTHORIZATION_FALLBACK_KEYS = [
        'REDIRECT_HTTP_AUTHORIZATION',
        'Authorization',
        'PHP_AUTH_DIGEST',
    ];

    public function __construct(private RuntimePathNormalizer $pathNormalizer)
    {
    }

    public function fromGlobals(array $routeParams = [], ?string $method = null, ?string $uri = null): Request
    {
        $resolvedMethod = $method ?? (string) ($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $resolvedUri = $uri ?? (string) (parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/');

        return $this->create(
            $_GET,
            $this->readJsonBodyFromInput(),
            $this->readHeaders(),
            $routeParams,
            $resolvedMethod,
            $resolvedUri
        );
    }

    public function create(
        array $query,
        array $body,
        array $headers,
        array $routeParams,
        string $method,
        string $uri
    ): Request {
        return new Request(
            query: $query,
            body: $body,
            headers: $headers,
            routeParams: $routeParams,
            method: $method,
            uri: $this->pathNormalizer->normalize($uri)
        );
    }

    private function readJsonBodyFromInput(): array
    {
        $body = [];
        $raw = file_get_contents('php://input');

        if ($raw !== '' && $raw !== false) {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $body = $decoded;
            }
        }

        return $body;
    }

    /**
     * @return array<string, mixed>
     */
    private function readHeaders(): array
    {
        $headers = $this->readHeadersFromServer();
        $this->mergeNativeHeaders($headers);
        $this->ensureAuthorizationHeader($headers);

        return $headers;
    }

    /**
     * @return array<string, mixed>
     */
    private function readHeadersFromServer(): array
    {
        $headers = [];
        foreach ($_SERVER as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $name = strtolower(str_replace('_', '-', substr($key, 5)));
                $headers[$name] = $value;
            }
        }

        return $headers;
    }

    /**
     * @param array<string, mixed> $headers
     */
    private function mergeNativeHeaders(array &$headers): void
    {
        if (!function_exists('getallheaders')) {
            return;
        }

        $nativeHeaders = getallheaders();
        if (!is_array($nativeHeaders)) {
            return;
        }

        foreach ($nativeHeaders as $name => $value) {
            if (is_string($name)) {
                $headers[strtolower($name)] = $value;
            }
        }
    }

    /**
     * @param array<string, mixed> $headers
     */
    private function ensureAuthorizationHeader(array &$headers): void
    {
        if (array_key_exists('authorization', $headers)) {
            return;
        }

        foreach (self::AUTHORIZATION_FALLBACK_KEYS as $key) {
            $value = $_SERVER[$key] ?? null;
            if (is_string($value) && $value !== '') {
                $headers['authorization'] = $value;
                break;
            }
        }
    }
}
