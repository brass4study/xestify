<?php

declare(strict_types=1);

namespace Xestify\plugins\infrastructure;

use Xestify\exceptions\PluginException;

final class PluginSchemaReader
{
    public function __construct(private string $pluginsDir)
    {
        $this->pluginsDir = rtrim($pluginsDir, '/\\');
    }

    /**
     * @param array<string, mixed> $manifest
     * @return array<string, mixed>|null
     */
    public function read(array $manifest): ?array
    {
        if (($manifest['type'] ?? '') !== 'entity') {
            return null;
        }

        $slug = (string) $manifest['slug'];
        $path = $this->pluginsDir . '/' . $slug . '/schema.json';

        if (!file_exists($path)) {
            throw new PluginException("schema.json not found for entity plugin: {$slug}");
        }

        $raw = file_get_contents($path);
        if ($raw === false) {
            throw new PluginException("Cannot read schema.json for entity plugin: {$slug}");
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded) || !isset($decoded['fields']) || !is_array($decoded['fields'])) {
            throw new PluginException("Invalid schema.json for entity plugin '{$slug}': missing fields");
        }

        return $decoded;
    }
}
