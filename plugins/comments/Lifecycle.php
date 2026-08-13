<?php

declare(strict_types=1);

namespace Xestify\plugins\comments;

use Xestify\plugins\contracts\PluginLifecycleInterface;

/**
 * Lifecycle for the comments plugin.
 *
 * The plugin_extension_data table is created by migration 003_plugin_extension_data.sql
 * and is shared by all extension plugins. This plugin needs no install-time setup of
 * its own: its registerTabs hook is wired at runtime by PluginHookRegistrar based on
 * `plugins.status`, and its per-entity availability is checked in Hooks::isEntityAllowed().
 * No PDO dependency is needed here (PluginClassLoader::instantiateLifecycle() supports
 * PDO-less constructors, same as instantiateHooks() always has).
 */
final class Lifecycle implements PluginLifecycleInterface
{
    public function onInstall(): void
    {
        // No install-time setup needed: plugin_extension_data is shared, created by
        // migration 003, not owned by this plugin.
    }

    public function onActivate(): void
    {
        // No action required for comments on activate: hook wiring is driven by
        // plugins.status via PluginHookRegistrar, not by a per-plugin flag here.
    }

    public function onDeactivate(): void
    {
        // No action required for comments on deactivate: see onActivate().
    }
}
