<?php

declare(strict_types=1);

namespace Xestify\plugins\application;

use OutOfBoundsException;
use Xestify\repositories\PluginRepository;

class PluginAdministrationService
{
    public function __construct(
        private PluginRepository $pluginRepository,
        private PluginSyncService $pluginSyncService,
        private PluginOutdatedService $pluginOutdatedService,
        private PluginUpdateService $pluginUpdateService,
        private PluginStatusService $pluginStatusService
    ) {
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listInstalled(): array
    {
        return $this->pluginRepository->listInstalled();
    }

    /**
     * @return array{
     *   summary: array{discovered:int, registered:int, unchanged:int, outdated:int, errors:int},
     *   plugins: array<string, array<string, mixed>>
     * }
     */
    public function syncAll(): array
    {
        return $this->pluginSyncService->syncAll();
    }

    /**
     * @return array<int, array{
     *   slug: string,
     *   name: string,
     *   plugin_type: string,
     *   installed_version: string,
     *   available_version: string
     * }>
     */
    public function getOutdated(): array
    {
        return $this->pluginOutdatedService->getOutdated();
    }

    /**
     * @return array{
     *   plugin: array<string, mixed>,
     *   update: array<string, mixed>
     * }
     */
    public function update(string $slug): array
    {
        return $this->pluginUpdateService->update($slug);
    }

    /**
     * @return array<string, mixed>
     */
    public function activate(string $slug): array
    {
        return $this->pluginStatusService->activate($slug);
    }

    /**
     * @return array<string, mixed>
     */
    public function deactivate(string $slug): array
    {
        return $this->pluginStatusService->deactivate($slug);
    }

    /**
     * @return array<string, mixed>
     */
    public function setStatus(string $slug, string $status): array
    {
        if ($status === 'active') {
            return $this->activate($slug);
        }

        if ($status === 'inactive') {
            return $this->deactivate($slug);
        }

        throw new OutOfBoundsException("Unsupported status '{$status}'.");
    }
}
