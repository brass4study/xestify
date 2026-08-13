<?php

declare(strict_types=1);

namespace Xestify\plugins\lifecycle;

use DomainException;
use OutOfBoundsException;
use PDO;
use Throwable;
use Xestify\repositories\PluginRepository;
use Xestify\repositories\PluginUpdateHistoryRepository;

final class PluginRollbackService
{
    public function __construct(
        private PDO $pdo,
        private PluginRepository $pluginRepository,
        private PluginUpdateHistoryRepository $historyRepository,
        private PluginLifecycleInvoker $lifecycleInvoker
    ) {
    }

    /**
     * @return array{
     *   plugin: array<string, mixed>,
     *   rollback: array{
     *     from_version: string,
     *     to_version: string,
     *     snapshot_id: string
     *   }
     * }
     */
    public function rollback(string $slug): array
    {
        $this->pdo->beginTransaction();

        try {
            $current = $this->pluginRepository->lockBySlug($slug);
            if ($current === null) {
                throw new OutOfBoundsException("Plugin '{$slug}' is not installed.");
            }

            $fromVersion = (string) ($current['version'] ?? '');
            $snapshot = $this->historyRepository->lockLatestSnapshotForRollback($slug, $fromVersion);
            if ($snapshot === null) {
                throw new DomainException(
                    "Plugin '{$slug}' has no snapshot available to rollback from version '{$fromVersion}'."
                );
            }

            $toVersion = (string) ($snapshot['version'] ?? '');
            $context = [
                'slug' => $slug,
                'from_version' => $fromVersion,
                'to_version' => $toVersion,
                'current_plugin' => $current,
                'snapshot' => $snapshot,
            ];

            $this->lifecycleInvoker->onRollback($slug, $context);

            $plugin = $this->pluginRepository->restoreFromSnapshot((string) $current['id'], $snapshot);

            if ((string) ($plugin['status'] ?? '') === 'active') {
                $this->lifecycleInvoker->onActivate($slug);
            }

            $this->pdo->commit();

            return [
                'plugin' => $plugin,
                'rollback' => [
                    'from_version' => $fromVersion,
                    'to_version' => $toVersion,
                    'snapshot_id' => (string) ($snapshot['id'] ?? ''),
                ],
            ];
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $e;
        }
    }
}
