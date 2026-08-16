<?php

declare(strict_types=1);

namespace Xestify\plugins\basic;

use Xestify\plugins\contracts\PluginLifecycleInterface;

/**
 * Lifecycle handler for the basic plugin.
 *
 * onInstall() → no action needed: PluginSyncService::installFromManifest()
 * already registers the plugin row and seeds schema_json from schema.json
 * before this runs.
 * onActivate() / onDeactivate() → no action needed for this plugin.
 */
final class Lifecycle implements PluginLifecycleInterface
{
    public function onInstall(): void
    {
        // No install-time setup needed: see class docblock.
    }

    public function onActivate(): void
    {
        // No action required for basic on activate
    }

    public function onDeactivate(): void
    {
        // No action required for basic on deactivate
    }
}
