<?php

declare(strict_types=1);

namespace Xestify\controllers;

use Xestify\core\AppDebug;
use Xestify\core\Response;

class HealthController
{
    public function index(): void
    {
        Response::make()->json([
            'version' => $this->resolveVersion(),
            'env'     => $_ENV['APP_ENV'] ?? 'unknown',
            'debug'   => AppDebug::enabled(),
        ]);
    }

    private function resolveVersion(): string
    {
        $versionFile = BASE_PATH . '/VERSION';

        if (is_file($versionFile)) {
            $version = trim((string) file_get_contents($versionFile));

            if ($version !== '') {
                return $version;
            }
        }

        // Sin backend/VERSION (entorno de desarrollo: no viene de un ZIP de
        // release), identificar el commit ayuda a saber que build hay
        // corriendo. Solo se intenta con APP_DEBUG=true: nunca en produccion.
        $devTag = AppDebug::enabled() ? $this->resolveDevGitTag() : null;

        return $devTag ?? 'dev';
    }

    private function resolveDevGitTag(): ?string
    {
        if (!function_exists('shell_exec')) {
            return null;
        }

        $repoRoot = dirname(BASE_PATH);
        $output = @shell_exec(sprintf('git -C %s rev-parse --short HEAD 2>&1', escapeshellarg($repoRoot)));
        $hash = is_string($output) ? trim($output) : '';

        return preg_match('/^[0-9a-f]{4,40}$/', $hash) === 1 ? "dev-$hash" : null;
    }
}
