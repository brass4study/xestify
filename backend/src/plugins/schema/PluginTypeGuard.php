<?php

declare(strict_types=1);

namespace Xestify\plugins\schema;

use DomainException;

/**
 * Guards against changing a plugin's registered type (entity/extension):
 * once installed, a plugin's type is fixed for the lifetime of the
 * installation. Shared by PluginSyncService (alta/sync) and
 * PluginUpdateService (update), which both compare the installed row
 * against the manifest on disk before writing anything.
 */
final class PluginTypeGuard
{
    /**
     * @param array<string, mixed> $installed
     * @param array<string, mixed> $manifest
     */
    public function assertTypeUnchanged(array $installed, array $manifest): void
    {
        if ((string) $installed['plugin_type'] !== (string) $manifest['type']) {
            throw new DomainException(
                "Plugin '{$manifest['slug']}' cannot change type from '{$installed['plugin_type']}'"
                . " to '{$manifest['type']}'."
            );
        }
    }
}
