<?php

declare(strict_types=1);

namespace Xestify\core;

class RuntimePathNormalizer
{
    private const API_PATH = '/api';
    private const API_PREFIX = '/api/';
    private const HEALTH_PATH = '/health';

    public function normalize(string $path): string
    {
        $normalizedPath = $path;

        if ($normalizedPath === '' || $normalizedPath === '/') {
            return '/';
        }

        if ($this->isDirectRuntimePath($normalizedPath)) {
            return $normalizedPath;
        }

        return $this->extractApiPath($normalizedPath)
            ?? $this->extractHealthPath($normalizedPath)
            ?? $normalizedPath;
    }

    private function isDirectRuntimePath(string $path): bool
    {
        return $path === self::HEALTH_PATH
            || $path === self::API_PATH
            || str_starts_with($path, self::API_PREFIX);
    }

    private function extractApiPath(string $path): ?string
    {
        $apiOffset = strpos($path, self::API_PREFIX);

        if ($apiOffset !== false && $apiOffset > 0) {
            return substr($path, $apiOffset);
        }

        return null;
    }

    private function extractHealthPath(string $path): ?string
    {
        $healthOffset = strpos($path, self::HEALTH_PATH);

        if ($healthOffset !== false && $healthOffset > 0) {
            $candidate = substr($path, $healthOffset);
            if ($candidate === self::HEALTH_PATH || str_starts_with($candidate, self::HEALTH_PATH . '/')) {
                return $candidate;
            }
        }

        return null;
    }
}
