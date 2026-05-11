<?php

declare(strict_types=1);

namespace Xestify\plugins\infrastructure;

final class PluginDiscoveryService
{
    public function __construct(private string $pluginsDir)
    {
        $this->pluginsDir = rtrim($pluginsDir, '/\\');
    }

    /**
     * @return list<string>
     */
    public function discover(): array
    {
        if (!is_dir($this->pluginsDir)) {
            return [];
        }

        $slugs = [];
        $entries = scandir($this->pluginsDir) ?: [];

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $pluginDir = $this->pluginsDir . '/' . $entry;
            if (!is_dir($pluginDir)) {
                continue;
            }

            if (file_exists($pluginDir . '/manifest.json')) {
                $slugs[] = $entry;
            }
        }

        return $slugs;
    }
}
