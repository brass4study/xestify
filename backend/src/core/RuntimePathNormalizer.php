<?php

declare(strict_types=1);

namespace Xestify\core;

class RuntimePathNormalizer
{
    private const RUNTIME_MARKERS = ['/api', '/health'];

    public function normalize(string $path): string
    {
        if ($path === '' || $path === '/') {
            return '/';
        }

        foreach (self::RUNTIME_MARKERS as $marker) {
            $offset = $this->findMarkerSegment($path, $marker);
            if ($offset !== null) {
                return substr($path, $offset);
            }
        }

        return $path;
    }

    private function findMarkerSegment(string $path, string $marker): ?int
    {
        $markerLength = strlen($marker);
        $offset = strpos($path, $marker);

        while ($offset !== false) {
            $afterMarker = $offset + $markerLength;
            if ($afterMarker === strlen($path) || $path[$afterMarker] === '/') {
                return $offset;
            }

            $offset = strpos($path, $marker, $offset + 1);
        }

        return null;
    }
}
