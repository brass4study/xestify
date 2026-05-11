<?php

declare(strict_types=1);

namespace Xestify\plugins\runtime;

use Xestify\plugins\HookDispatcher;
use Xestify\plugins\infrastructure\PluginClassLoader;
use Xestify\repositories\PluginRepository;

final class PluginHookRegistrar
{
    public function __construct(
        private PluginRepository $pluginRepository,
        private PluginClassLoader $classLoader
    ) {
    }

    public function registerActiveHooks(HookDispatcher $dispatcher): void
    {
        foreach ($this->pluginRepository->listActiveSlugs() as $slug) {
            $hooks = $this->classLoader->instantiateHooks($slug);
            if ($hooks !== null) {
                $hooks->register($dispatcher); // NOSONAR - plugin hook convention
            }
        }
    }
}
