<?php

declare(strict_types=1);

namespace Xestify\plugins\discovery;

use Xestify\exceptions\PluginException;

final class PluginManifestReader
{
    private const REQUIRED_FIELDS = ['name', 'label', 'version', 'type', 'core_version'];
    private const VALID_TYPES = ['entity', 'extension'];

    public function __construct(private string $pluginsDir)
    {
        $this->pluginsDir = rtrim($pluginsDir, '/\\');
    }

    /**
     * @return array<string, mixed>
     */
    public function read(string $slug): array
    {
        $path = $this->pluginsDir . '/' . $slug . '/manifest.json';

        if (!file_exists($path)) {
            throw new PluginException("manifest.json not found for plugin: {$slug}");
        }

        $json = file_get_contents($path);
        if ($json === false) {
            throw new PluginException("Cannot read manifest.json for plugin: {$slug}");
        }

        $data = json_decode($json, true);
        if (!is_array($data)) {
            throw new PluginException("Invalid JSON in manifest.json for plugin: {$slug}");
        }

        $this->validateStructure($data, $slug);

        return $data;
    }

    /**
     * @param array<string, mixed> $manifest
     */
    private function validateStructure(array $manifest, string $slug): void
    {
        foreach (self::REQUIRED_FIELDS as $field) {
            $value = $manifest[$field] ?? null;
            if (!is_string($value) || $value === '') {
                throw new PluginException(
                    "manifest.json for plugin '{$slug}' is missing required field: {$field}"
                );
            }
        }

        $type = (string) $manifest['type'];
        if (!in_array($type, self::VALID_TYPES, true)) {
            throw new PluginException(
                "Plugin '{$slug}' has invalid type '{$type}'. Must be one of: "
                . implode(', ', self::VALID_TYPES)
            );
        }
    }
}
