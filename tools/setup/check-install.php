<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/cli-helpers.php';

/*
 * Post-installation web exposure check (CLI only). Requests the public routes
 * and a list of paths that must NEVER be served (setup scripts, backend/.env,
 * plugin PHP/JSON, docs, dotfiles — including upper-case variants that bypass a
 * case-sensitive rewrite on case-insensitive filesystems) against the base URL
 * of a running installation, and exits 1 if anything sensitive answers with its
 * real content. A 200 whose body is the SPA shell (catch-all fallback of the
 * root .htaccess) is fine: the file itself was not served.
 */

const CHECK_FLAGS_BOOL = ['help'];
const CHECK_FLAGS_VALUE = ['url'];
const CHECK_TIMEOUT_SECONDS = 5;
const CHECK_HTTP_OK = 200;
const CHECK_BLOCKED_STATUSES = [401, 403, 404];
const CHECK_EXPECTED_BLOCKED = '403/404';

const CHECK_PUBLIC_PATHS = ['health', ''];
const CHECK_BLOCKED_PATHS = [
    'tools/setup/install.php',
    'Tools/setup/install.php',
    'tools/setup/bootstrap.php',
    'tools/dev/switch-demoinventory-version.php',
    'backend/.env',
    'Backend/.env',
    'backend/.env.example',
    'backend/public/index.php',
    'backend/src/config/app.php',
    'backend/database/schema/001_users.sql',
    'plugins/comments/Hooks.php',
    'plugins/comments/manifest.json',
    'plugins/persons/schema.json',
    'INSTALL.md',
    'README.md',
    '.htaccess',
    '.git/HEAD',
    'docs/README.md',
    'skills/README.md',
];
const CHECK_WARN_PATHS = ['tests/'];

function checkUsage(): string
{
    return <<<TXT
Uso: php tools/setup/check-install.php --url=http://host/[subruta/]

Comprueba, contra una instalacion en marcha, que las rutas publicas responden
(/health, la SPA) y que ninguna ruta interna se sirve por web (scripts de
tools/, backend/.env, PHP/JSON de plugins, docs, dotfiles, variantes en
mayusculas). Sale con codigo 1 si algo sensible esta expuesto.

TXT;
}

/**
 * @return array{status: int, body: string, error: string}
 */
function checkFetch(string $url): array
{
    if (function_exists('curl_init')) {
        return checkFetchWithCurl($url);
    }

    return checkFetchWithStream($url);
}

/**
 * @return array{status: int, body: string, error: string}
 */
function checkFetchWithCurl(string $url): array
{
    $handle = curl_init($url);
    curl_setopt_array($handle, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_TIMEOUT => CHECK_TIMEOUT_SECONDS,
        CURLOPT_CONNECTTIMEOUT => CHECK_TIMEOUT_SECONDS,
    ]);
    $body = curl_exec($handle);
    $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
    $error = curl_error($handle);

    return ['status' => $status, 'body' => is_string($body) ? $body : '', 'error' => $error];
}

/**
 * @return array{status: int, body: string, error: string}
 */
function checkFetchWithStream(string $url): array
{
    $context = stream_context_create(['http' => [
        'method' => 'GET',
        'ignore_errors' => true,
        'timeout' => CHECK_TIMEOUT_SECONDS,
        'follow_location' => 0,
    ]]);
    $body = file_get_contents($url, false, $context);
    $headers = $http_response_header ?? [];
    $status = 0;
    if (isset($headers[0]) && preg_match('#\s(\d{3})\s#', $headers[0] . ' ', $matches) === 1) {
        $status = (int) $matches[1];
    }

    return [
        'status' => $status,
        'body' => is_string($body) ? $body : '',
        'error' => $body === false ? 'sin respuesta' : '',
    ];
}

function checkPrintRow(string $path, string $expected, string $got, string $verdict): void
{
    printf("  %-48s %-14s %-22s %s\n", '/' . $path, $expected, $got, $verdict);
}

/**
 * @param array{status: int, body: string, error: string} $response
 * @return bool true when the public path answered as expected
 */
function checkReportPublicPath(string $path, array $response): bool
{
    $ok = $response['status'] === CHECK_HTTP_OK && $response['error'] === '';
    $got = (string) $response['status'] . ($response['error'] !== '' ? ' ' . $response['error'] : '');
    checkPrintRow($path, '200', $got, $ok ? 'OK' : 'FALLO');

    return $ok;
}

/**
 * A blocked path is safe when it answers 401/403/404, or 200 with exactly the
 * SPA shell (root .htaccess catch-all), which means the file was not served.
 *
 * @return bool true when the path is NOT exposed
 */
function checkBlockedPath(string $baseUrl, string $path, string $spaBody): bool
{
    $response = checkFetch($baseUrl . $path);
    $status = $response['status'];

    if (in_array($status, CHECK_BLOCKED_STATUSES, true)) {
        checkPrintRow($path, CHECK_EXPECTED_BLOCKED, (string) $status, 'OK');
        return true;
    }
    if ($status === CHECK_HTTP_OK && $spaBody !== '' && $response['body'] === $spaBody) {
        checkPrintRow($path, CHECK_EXPECTED_BLOCKED, '200 (shell SPA)', 'OK');
        return true;
    }
    if ($status === 0) {
        checkPrintRow($path, CHECK_EXPECTED_BLOCKED, 'sin respuesta ' . $response['error'], 'FALLO');
        return false;
    }

    checkPrintRow($path, CHECK_EXPECTED_BLOCKED, (string) $status . ' (contenido real)', 'EXPUESTO');

    return false;
}

function checkWarnPath(string $baseUrl, string $path): void
{
    $response = checkFetch($baseUrl . $path);
    $status = $response['status'];
    $verdict = $status === CHECK_HTTP_OK ? 'AVISO (solo desarrollo)' : 'OK';
    checkPrintRow($path, '403 en prod', (string) $status, $verdict);
}

// ---------------------------------------------------------------------------
// Main
// ---------------------------------------------------------------------------

$options = cliParseOptions($argv, CHECK_FLAGS_BOOL, CHECK_FLAGS_VALUE, checkUsage());
if ($options['help'] === true) {
    echo checkUsage();
    exit(CLI_EXIT_OK);
}

$baseUrl = rtrim((string) $options['url'], '/') . '/';
if ((string) $options['url'] === '' || filter_var($baseUrl, FILTER_VALIDATE_URL) === false) {
    cliFail("Indica una URL base valida con --url=http://host/\n\n" . checkUsage(), CLI_EXIT_USAGE);
}

echo "=== Comprobacion de instalacion: {$baseUrl} ===\n\n";
printf("  %-48s %-14s %-22s %s\n", 'Ruta', 'Esperado', 'Obtenido', 'Resultado');

$allOk = true;
$spaBody = '';
foreach (CHECK_PUBLIC_PATHS as $path) {
    $response = checkFetch($baseUrl . $path);
    $allOk = checkReportPublicPath($path, $response) && $allOk;
    if ($path === '') {
        $spaBody = $response['body'];
    }
}

foreach (CHECK_BLOCKED_PATHS as $path) {
    $allOk = checkBlockedPath($baseUrl, $path, $spaBody) && $allOk;
}
foreach (CHECK_WARN_PATHS as $path) {
    checkWarnPath($baseUrl, $path);
}

if (!$allOk) {
    cliFail(
        'La instalacion expone rutas internas o las rutas publicas no responden. Revisa DocumentRoot, '
        . 'AllowOverride All y mod_rewrite (INSTALL.md, seccion "Modelo de seguridad de la instalacion").'
    );
}

echo "\nOK: rutas publicas operativas y rutas internas no accesibles.\n";
exit(CLI_EXIT_OK);
