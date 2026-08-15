<?php

declare(strict_types=1);

namespace Xestify\plugins\lifecycle;

use Xestify\plugins\contracts\PluginLifecycleUpdateInterface;

final class PluginLifecycleInvoker
{
    public function __construct(private PluginClassLoader $classLoader)
    {
    }

    public function onInstall(string $pluginName): void
    {
        $lifecycle = $this->classLoader->instantiateLifecycle($pluginName);
        if ($lifecycle !== null) {
            $lifecycle->onInstall();
        }
    }

    public function onActivate(string $pluginName): void
    {
        $lifecycle = $this->classLoader->instantiateLifecycle($pluginName);
        if ($lifecycle !== null) {
            $lifecycle->onActivate();
        }
    }

    public function onDeactivate(string $pluginName): void
    {
        $lifecycle = $this->classLoader->instantiateLifecycle($pluginName);
        if ($lifecycle !== null) {
            $lifecycle->onDeactivate();
        }
    }

    /**
     * @param array<string, mixed> $context
     */
    public function onUpdate(string $pluginName, array $context): void
    {
        $lifecycle = $this->classLoader->instantiateLifecycle($pluginName);
        if ($lifecycle instanceof PluginLifecycleUpdateInterface) {
            $lifecycle->onUpdate($context);
        }
    }

    /**
     * @param array<string, mixed> $context
     */
    public function onRollback(string $pluginName, array $context): void
    {
        $lifecycle = $this->classLoader->instantiateLifecycle($pluginName);
        if ($lifecycle instanceof PluginLifecycleUpdateInterface) {
            $lifecycle->onRollback($context);
        }
    }
}
