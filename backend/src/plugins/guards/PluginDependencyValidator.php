<?php

declare(strict_types=1);

namespace Xestify\plugins\guards;

use Xestify\exceptions\PluginException;
use Xestify\repositories\PluginRepository;

final class PluginDependencyValidator
{
    public function __construct(private PluginRepository $pluginRepository)
    {
    }

    /**
     * @param array<string, mixed> $manifest
     */
    public function validate(array $manifest): void
    {
        $requires = $manifest['requires'] ?? [];

        if (!is_array($requires) || $requires === []) {
            return;
        }

        foreach ($requires as $dependency) {
            if (!is_array($dependency) || !isset($dependency['slug']) || !is_string($dependency['slug'])) {
                throw new PluginException(
                    "Plugin '{$manifest['slug']}' has an invalid 'requires' entry in manifest.json"
                );
            }

            $depSlug = $dependency['slug'];
            $minVersion = isset($dependency['version']) && is_string($dependency['version'])
                ? $dependency['version']
                : '0.0.0';

            $installedVersion = $this->pluginRepository->findInstalledVersion($depSlug);
            if ($installedVersion === null) {
                throw new PluginException(
                    "Plugin '{$manifest['slug']}' requires plugin '{$depSlug}' which is not installed"
                );
            }

            if (version_compare($installedVersion, $minVersion, '<')) {
                throw new PluginException(
                    "Plugin '{$manifest['slug']}' requires plugin '{$depSlug}' >= {$minVersion}, "
                    . "installed version is {$installedVersion}"
                );
            }
        }
    }
}
