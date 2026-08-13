<?php

declare(strict_types=1);

namespace Xestify\plugins\lifecycle;

use Xestify\core\HookDispatcher;
use Xestify\repositories\PluginRepository;

final class PluginHookRegistrar
{
    /** @var array<string, true> Slugs already registered by this instance. */
    private array $registeredSlugs = [];

    public function __construct(
        private PluginRepository $pluginRepository,
        private PluginClassLoader $classLoader
    ) {
    }

    /**
     * Idempotent per registrar instance: calling this more than once with the
     * same $dispatcher will not register a slug's hooks twice. The registrar
     * has a 1:1 lifetime with its dispatcher in production (both are built
     * fresh, once per request, by the DI container) — this guard exists for
     * callers (tests, future code) that might invoke it more than once.
     */
    public function registerActiveHooks(HookDispatcher $dispatcher): void
    {
        foreach ($this->pluginRepository->listActiveSlugs() as $slug) {
            if (isset($this->registeredSlugs[$slug])) {
                continue;
            }

            $hooks = $this->classLoader->instantiateHooks($slug);
            if ($hooks !== null) {
                $hooks->register($dispatcher); // NOSONAR - plugin hook convention
                $this->registeredSlugs[$slug] = true;
            }
        }
    }
}
