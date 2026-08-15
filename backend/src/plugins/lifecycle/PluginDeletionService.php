<?php

declare(strict_types=1);

namespace Xestify\plugins\lifecycle;

use Xestify\controllers\ExtensionPluginDataStore;
use Xestify\repositories\GenericRepository;
use Xestify\repositories\PluginUpdateHistoryRepository;
use Xestify\repositories\PluginWriteRepository;

/**
 * Deletes a plugin instance and all its associated data (STORY 10.3 §7).
 * Assumes it runs inside a transaction already opened by its caller
 * (PluginAdministrationService::deletePlugin()) — this service does not
 * begin/commit/roll back its own transaction. Deletion is allowed in any
 * status: if the plugin is active, onDeactivate() runs first so the plugin's
 * own cleanup logic still fires before its data disappears.
 */
final class PluginDeletionService
{
    public function __construct(
        private PluginWriteRepository $pluginWriteRepository,
        private GenericRepository $genericRepository,
        private ExtensionPluginDataStore $extensionPluginDataStore,
        private PluginUpdateHistoryRepository $historyRepository,
        private PluginLifecycleInvoker $lifecycleInvoker
    ) {
    }

    /**
     * @param array<string, mixed> $plugin locked row (manifest_json decoded), from PluginRepository::lockBySlug()
     */
    public function deletePlugin(array $plugin): void
    {
        $slug = (string) $plugin['slug'];
        $pluginName = (string) ($plugin['manifest_json']['name'] ?? '');
        $pluginType = (string) ($plugin['manifest_json']['type'] ?? '');

        if ((string) ($plugin['status'] ?? '') === 'active') {
            $this->lifecycleInvoker->onDeactivate($pluginName);
        }

        if ($pluginType === 'entity') {
            $this->genericRepository->deleteByEntitySlug($slug);
            $this->extensionPluginDataStore->deleteByEntitySlug($slug);
        }

        if ($pluginType === 'extension') {
            $this->extensionPluginDataStore->deleteByPluginSlug($slug);
        }

        $this->historyRepository->deleteBySlug($slug);
        $this->pluginWriteRepository->deleteById((string) $plugin['id']);
    }
}
