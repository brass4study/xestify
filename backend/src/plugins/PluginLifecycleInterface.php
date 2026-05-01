<?php

declare(strict_types=1);

namespace Xestify\plugins;

/**
 * Contract for plugin lifecycle hooks.
 *
 * A plugin may ship a Lifecycle.php class implementing this interface.
 * PluginLoader will discover it by convention: Xestify\plugins\{slug}\Lifecycle.
 *
 * All methods are called by PluginLoader at the appropriate moment:
 *   - onInstall()    → first time the plugin is registered in plugins_registry
 *   - onActivate()   → when the plugin status changes to 'active'
 *   - onDeactivate() → when the plugin status changes to 'inactive'
 */
interface PluginLifecycleInterface
{
    public function onInstall(): void;

    public function onActivate(): void;

    public function onDeactivate(): void;
}

