<?php

declare(strict_types=1);

namespace Xestify\plugins\runtime;

use Xestify\plugins\infrastructure\PluginClassLoader;

final class PluginLifecycleInvoker
{
    public function __construct(private PluginClassLoader $classLoader)
    {
    }

    public function onInstall(string $slug): void
    {
        $lifecycle = $this->classLoader->instantiateLifecycle($slug);
        if ($lifecycle !== null) {
            $lifecycle->onInstall();
        }
    }

    public function onActivate(string $slug): void
    {
        $lifecycle = $this->classLoader->instantiateLifecycle($slug);
        if ($lifecycle !== null) {
            $lifecycle->onActivate();
        }
    }

    public function onDeactivate(string $slug): void
    {
        $lifecycle = $this->classLoader->instantiateLifecycle($slug);
        if ($lifecycle !== null) {
            $lifecycle->onDeactivate();
        }
    }

    /**
     * @param array<string, mixed> $context
     */
    public function onUpdate(string $slug, array $context): void
    {
        $lifecycle = $this->classLoader->instantiateLifecycle($slug);
        if ($lifecycle !== null && method_exists($lifecycle, 'onUpdate')) {
            $lifecycle->onUpdate($context); // @phpstan-ignore-line optional lifecycle convention
        }
    }

    /**
     * @param array<string, mixed> $context
     */
    public function onRollback(string $slug, array $context): void
    {
        $lifecycle = $this->classLoader->instantiateLifecycle($slug);
        if ($lifecycle !== null && method_exists($lifecycle, 'onRollback')) {
            $lifecycle->onRollback($context); // @phpstan-ignore-line optional lifecycle convention
        }
    }
}
