<?php

declare(strict_types=1);

namespace Xestify\plugins\demoinventory;

use PDO;
use Xestify\plugins\PluginLifecycleInterface;

final class Lifecycle implements PluginLifecycleInterface
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
        $this->touchUpdateContext($context);
    }

    /**
     * @param array<string, mixed> $context
     */
    public function onRollback(array $context): void
    {
        // Demo plugin: rollback path does not need custom restore logic.
        $this->pingDatabase();
        $this->touchRollbackContext($context);
    }

    private function pingDatabase(): void
    {
        $this->pdo->query('SELECT 1');
    }

    /**
     * @param array<string, mixed> $context
     */
    private function touchUpdateContext(array $context): void
    {
        $toVersion = (string) ($context['to_version'] ?? '');
        if ($toVersion !== '') {
            $this->pingDatabase();
        }
    }

    /**
     * @param array<string, mixed> $context
     */
    private function touchRollbackContext(array $context): void
    {
        $fromVersion = (string) ($context['from_version'] ?? '');
        if ($fromVersion !== '') {
            $this->pingDatabase();
        }
    }
}
