<?php

declare(strict_types=1);

namespace Xestify\plugins\application;

use OutOfBoundsException;
use PDO;
use Throwable;
use Xestify\plugins\runtime\PluginLifecycleInvoker;
use Xestify\repositories\PluginRepository;

final class PluginStatusService
{
    public function __construct(
        private PDO $pdo,
        private PluginRepository $pluginRepository,
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
            $plugin = $this->pluginRepository->updateStatus($slug, 'active');
            if ($plugin === null) {
                throw new OutOfBoundsException("Plugin '{$slug}' is not installed.");
            }

            $this->lifecycleInvoker->onActivate($slug);

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
            $plugin = $this->pluginRepository->updateStatus($slug, 'inactive');
            if ($plugin === null) {
                throw new OutOfBoundsException("Plugin '{$slug}' is not installed.");
            }

            $this->lifecycleInvoker->onDeactivate($slug);

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
