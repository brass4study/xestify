<?php

declare(strict_types=1);

namespace Xestify\plugins\contracts;

/**
 * Optional extension of PluginLifecycleInterface for plugins that need to
 * react to updates/rollbacks. Not every plugin implements it; the runtime
 * checks via `instanceof` before invoking (see PluginLifecycleInvoker).
 */
interface PluginLifecycleUpdateInterface extends PluginLifecycleInterface
{
    /** @param array<string, mixed> $context */
    public function onUpdate(array $context): void;

    /** @param array<string, mixed> $context */
    public function onRollback(array $context): void;
}
