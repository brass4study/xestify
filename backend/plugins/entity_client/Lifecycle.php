<?php

declare(strict_types=1);

namespace Xestify\plugins\entity_client;

use PDO;
use Xestify\plugins\PluginLifecycleInterface;

require_once __DIR__ . '/Installer.php';

/**
 * Lifecycle handler for the entity_client plugin.
 *
 * onInstall() → runs the Installer to register the entity and seed its schema.
 * onActivate() / onDeactivate() → no action needed for this plugin.
 */
final class Lifecycle implements PluginLifecycleInterface
{
    public function __construct(private PDO $pdo)
    {
    }

    public function onInstall(): void
    {
        (new Installer($this->pdo))->install();
    }

    public function onActivate(): void
    {
        // No action required for entity_client on activate
    }

    public function onDeactivate(): void
    {
        // No action required for entity_client on deactivate
    }
}
