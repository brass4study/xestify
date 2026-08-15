<?php

declare(strict_types=1);

namespace Xestify\plugins\lifecycle;

use Xestify\plugins\discovery\PluginSourceService;
use Xestify\repositories\PluginRepository;

final class PluginOutdatedService
{
    public function __construct(
        private PluginSourceService $pluginSource,
        private PluginRepository $pluginRepository
    ) {
    }

    /**
     * @return array<int, array{
     *   slug: string,
     *   name: string,
     *   plugin_type: string,
     *   installed_version: string,
     *   available_version: string
     * }>
     */
    public function getOutdated(): array
    {
        // plugin_name is not unique (STORY 10.3): a disk folder can back
        // several instances, so each plugin_name maps to a list of rows, and
        // every instance is checked against disk independently.
        $dbPlugins = $this->pluginRepository->listInstalledVersions();
        $dbByPluginName = [];

        foreach ($dbPlugins as $row) {
            $dbByPluginName[(string) $row['plugin_name']][] = $row;
        }

        $outdated = [];
        foreach ($this->pluginSource->discover() as $pluginName) {
            $manifest = $this->pluginSource->readManifest($pluginName);
            if (!isset($dbByPluginName[$pluginName])) {
                continue;
            }

            $diskVersion = (string) ($manifest['version'] ?? '');
            if ($diskVersion === '') {
                continue;
            }

            foreach ($dbByPluginName[$pluginName] as $installed) {
                $dbVersion = (string) ($installed['version'] ?? '');
                if ($dbVersion === '') {
                    continue;
                }

                if (version_compare($diskVersion, $dbVersion, '>')) {
                    $outdated[] = [
                        'slug' => (string) $installed['slug'],
                        'name' => (string) ($manifest['label'] ?? $pluginName),
                        'plugin_type' => (string) ($manifest['type'] ?? ''),
                        'installed_version' => $dbVersion,
                        'available_version' => $diskVersion,
                    ];
                }
            }
        }

        return $outdated;
    }
}
