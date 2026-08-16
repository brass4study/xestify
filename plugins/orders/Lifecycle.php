<?php

declare(strict_types=1);

namespace Xestify\plugins\orders;

use Xestify\plugins\contracts\PluginLifecycleInterface;

/**
 * Lifecycle handler for the orders plugin.
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
        // No action required for orders on activate
    }

    public function onDeactivate(): void
    {
        // No action required for orders on deactivate
    }
}
