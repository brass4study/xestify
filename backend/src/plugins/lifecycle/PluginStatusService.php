<?php

declare(strict_types=1);

namespace Xestify\plugins\lifecycle;

use OutOfBoundsException;
use PDO;
use Throwable;
use Xestify\repositories\PluginWriteRepository;

final class PluginStatusService
{
    public function __construct(
        private PDO $pdo,
        private PluginWriteRepository $pluginWriteRepository,
        private PluginLifecycleInvoker $lifecycleInvoker
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function activate(string $slug): array
    {
        $this->pdo->beginTransaction();

        try {
            $plugin = $this->pluginWriteRepository->updateStatus($slug, 'active');
            if ($plugin === null) {
                throw new OutOfBoundsException("Plugin '{$slug}' is not installed.");
            }

            $this->lifecycleInvoker->onActivate((string) ($plugin['manifest_json']['name'] ?? ''));

            $this->pdo->commit();

            return $plugin;
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $e;
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function deactivate(string $slug): array
    {
        $this->pdo->beginTransaction();

        try {
            $plugin = $this->pluginWriteRepository->updateStatus($slug, 'inactive');
            if ($plugin === null) {
                throw new OutOfBoundsException("Plugin '{$slug}' is not installed.");
            }

            $this->lifecycleInvoker->onDeactivate((string) ($plugin['manifest_json']['name'] ?? ''));

            $this->pdo->commit();

            return $plugin;
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $e;
        }
    }
}
