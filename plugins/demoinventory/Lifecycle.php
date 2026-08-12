<?php

declare(strict_types=1);

namespace Xestify\plugins\demoinventory;

use PDO;
use Xestify\plugins\PluginLifecycleUpdateInterface;

final class Lifecycle implements PluginLifecycleUpdateInterface
{
    public function __construct(private PDO $pdo)
    {
    }

    public function onInstall(): void
    {
        // Demo plugin: validate DB wiring on install without domain side effects.
        $this->pingDatabase();
    }

    public function onActivate(): void
    {
        // Demo plugin: no activation work required.
        $this->pingDatabase();
    }

    public function onDeactivate(): void
    {
        // Demo plugin: no deactivation work required.
        $this->pingDatabase();
    }

    /**
     * @param array<string, mixed> $context
     */
    public function onUpdate(array $context): void
    {
        // Demo plugin: update path does not need migrations for additive schema changes.
        $this->pingDatabase();
    }

    /**
     * @param array<string, mixed> $context
     */
    public function onRollback(array $context): void
    {
        // Demo plugin: rollback path does not need custom restore logic.
        $this->pingDatabase();
    }

    private function pingDatabase(): void
    {
        $this->pdo->query('SELECT 1');
    }
}
