<?php

declare(strict_types=1);

namespace Xestify\plugins\application;

use OutOfBoundsException;
use Xestify\plugins\runtime\PluginLifecycleInvoker;
use Xestify\repositories\PluginRepository;

final class PluginStatusService
{
    public function __construct(
        private PluginRepository $pluginRepository,
        private PluginLifecycleInvoker $lifecycleInvoker
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function activate(string $slug): array
    {
        $plugin = $this->pluginRepository->updateStatus($slug, 'active');
        if ($plugin === null) {
            throw new OutOfBoundsException("Plugin '{$slug}' is not installed.");
        }

        $this->lifecycleInvoker->onActivate($slug);

        return $plugin;
    }

    /**
     * @return array<string, mixed>
     */
    public function deactivate(string $slug): array
    {
        $plugin = $this->pluginRepository->updateStatus($slug, 'inactive');
        if ($plugin === null) {
            throw new OutOfBoundsException("Plugin '{$slug}' is not installed.");
        }

        $this->lifecycleInvoker->onDeactivate($slug);

        return $plugin;
    }
}
