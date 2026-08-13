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
        $dbPlugins = $this->pluginRepository->listInstalledVersions();
        $dbBySlug = [];

        foreach ($dbPlugins as $row) {
            $dbBySlug[(string) $row['slug']] = $row;
        }

        $outdated = [];
        foreach ($this->pluginSource->discover() as $slug) {
            $manifest = $this->pluginSource->readManifest($slug);
            if (!isset($dbBySlug[$slug])) {
                continue;
            }

            $diskVersion = (string) ($manifest['version'] ?? '');
            $dbVersion = (string) ($dbBySlug[$slug]['version'] ?? '');
            if ($diskVersion === '' || $dbVersion === '') {
                continue;
            }

            if (version_compare($diskVersion, $dbVersion, '>')) {
                $outdated[] = [
                    'slug' => $slug,
                    'name' => (string) ($manifest['name'] ?? $slug),
                    'plugin_type' => (string) ($manifest['type'] ?? ''),
                    'installed_version' => $dbVersion,
                    'available_version' => $diskVersion,
                ];
            }
        }

        return $outdated;
    }
}
