<?php

declare(strict_types=1);

namespace Xestify\plugins\comments;

use PDO;
use Xestify\plugins\PluginLifecycleInterface;

/**
 * Lifecycle for the comments plugin.
 *
 * The plugin_extension_data table is created by migration 003_plugin_extension_data.sql
 * and is shared by all extension plugins. This lifecycle only ensures the
 * plugin_hook_registry entry exists for the registerTabs hook.
 */
final class Lifecycle implements PluginLifecycleInterface
{
    private const PLUGIN_SLUG = 'comments';
    private const HOOK_NAME   = 'registerTabs';

    public function __construct(private PDO $pdo)
    {
    }

    /**
     * Called on first install: registers the registerTabs hook in plugin_hook_registry.
     */
    public function onInstall(): void
    {
        $this->pdo->prepare(
            'INSERT INTO plugin_hooks (slug, target_entity_slug, hook_name, priority)
             VALUES (:slug, :target, :hook, :priority)
             ON CONFLICT DO NOTHING'
        )->execute([
            ':slug'     => self::PLUGIN_SLUG,
            ':target'   => '*',
            ':hook'     => self::HOOK_NAME,
            ':priority' => 50,
        ]);
    }

    /**
     * Called when plugin is activated.
     */
    public function onActivate(): void
    {
        $this->pdo->prepare(
            'UPDATE plugin_hooks SET enabled = true
              WHERE slug = :slug AND hook_name = :hook'
        )->execute([':slug' => self::PLUGIN_SLUG, ':hook' => self::HOOK_NAME]);
    }

    /**
     * Called when plugin is deactivated.
     */
    public function onDeactivate(): void
    {
        $this->pdo->prepare(
            'UPDATE plugin_hooks SET enabled = false
              WHERE slug = :slug AND hook_name = :hook'
        )->execute([':slug' => self::PLUGIN_SLUG, ':hook' => self::HOOK_NAME]);
    }
}
