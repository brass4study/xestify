<?php

declare(strict_types=1);

namespace Xestify\plugins\invoices;

use Xestify\plugins\contracts\PluginLifecycleInterface;

/**
 * Lifecycle handler for the invoices plugin.
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
        // No action required for invoices on activate
    }

    public function onDeactivate(): void
    {
        // No action required for invoices on deactivate
    }
}
