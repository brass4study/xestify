<?php

declare(strict_types=1);

$frontendRoot = realpath(__DIR__ . '/../../frontend/src');
$backendBaseUrl = 'http://localhost:8080';

if ($frontendRoot === false) {
    http_response_code(500);
    echo 'Frontend root not found.';
    return;
}

$requestUri = $_SERVER['REQUEST_URI'] ?? '/';
$path = parse_url($requestUri, PHP_URL_PATH);
$path = is_string($path) ? $path : '/';

if (str_starts_with($path, '/api/')) {
    proxyApiRequest($backendBaseUrl, $requestUri);
    return;
}

serveFrontendAsset($frontendRoot, $path);

function proxyApiRequest(string $backendBaseUrl, string $requestUri): void
{
    $targetUrl = rtrim($backendBaseUrl, '/') . $requestUri;
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $body = file_get_contents('php://input');

    $headers = collectRequestHeaders();
    $filteredHeaders = [];

    foreach ($headers as $name => $value) {
        $lower = strtolower($name);
        if ($lower === 'host' || $lower === 'content-length' || $lower === 'connection') {
            continue;
        }
        $filteredHeaders[] = $name . ': ' . $value;
    }

    $context = stream_context_create([
        'http' => [
            'method' => $method,
            'header' => implode("\r\n", $filteredHeaders),
            'content' => $body === false ? '' : $body,
            'ignore_errors' => true,
            'timeout' => 30,
        ],
    ]);

    $responseBody = file_get_contents($targetUrl, false, $context);
    $responseHeaders = [];
    if (function_exists('http_get_last_response_headers')) {
        $headers = http_get_last_response_headers();
        if (is_array($headers)) {
            $responseHeaders = $headers;
        }
    }

    if (isset($responseHeaders[0]) && preg_match('/\s(\d{3})\s?/', $responseHeaders[0], $matches) === 1) {
        http_response_code((int) $matches[1]);
    }

    foreach ($responseHeaders as $headerLine) {
        if (strpos($headerLine, ':') === false) {
            continue;
        }

        [$headerName, $headerValue] = explode(':', $headerLine, 2);
        $headerName = trim($headerName);
        $headerValue = trim($headerValue);

        $lower = strtolower($headerName);
        if ($lower === 'transfer-encoding' || $lower === 'connection' || $lower === 'content-length') {
            continue;
        }

        header($headerName . ': ' . $headerValue, true);
    }

    echo $responseBody === false ? '' : $responseBody;
}

function serveFrontendAsset(string $frontendRoot, string $path): void
{
    $safePath = $path === '/' ? '/index.html' : $path;
    $candidate = realpath($frontendRoot . $safePath);

    if ($candidate !== false && str_starts_with($candidate, $frontendRoot) && is_file($candidate)) {
        $mime = detectMimeType($candidate);
        if (is_string($mime) && $mime !== '') {
            header('Content-Type: ' . $mime);
        }
        readfile($candidate);
        return;
    }

    $indexFile = $frontendRoot . '/index.html';
    if (!is_file($indexFile)) {
        http_response_code(404);
        echo 'Frontend index not found.';
        return;
    }

    header('Content-Type: text/html; charset=UTF-8');
    readfile($indexFile);
}

function collectRequestHeaders(): array
{
    if (function_exists('getallheaders')) {
        $headers = getallheaders();
        if (is_array($headers)) {
            return $headers;
        }
    }

    $result = [];
    foreach ($_SERVER as $key => $value) {
        if (!str_starts_with($key, 'HTTP_')) {
            continue;
        }

        $name = str_replace('_', '-', substr($key, 5));
        $name = ucwords(strtolower($name), '-');
        $result[$name] = (string) $value;
    }

    return $result;
}

function detectMimeType(string $path): string
{
    if (function_exists('mime_content_type')) {
        $detected = mime_content_type($path);
        if (is_string($detected) && $detected !== '') {
            return $detected;
        }
    }

    $extension = strtolower((string) pathinfo($path, PATHINFO_EXTENSION));
    $map = [
        'html' => 'text/html; charset=UTF-8',
        'css' => 'text/css; charset=UTF-8',
        'js' => 'application/javascript; charset=UTF-8',
        'json' => 'application/json; charset=UTF-8',
        'svg' => 'image/svg+xml',
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'gif' => 'image/gif',
        'webp' => 'image/webp',
        'ico' => 'image/x-icon',
    ];

    return $map[$extension] ?? 'application/octet-stream';
}
