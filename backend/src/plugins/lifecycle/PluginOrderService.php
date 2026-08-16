<?php

declare(strict_types=1);

namespace Xestify\plugins\lifecycle;

use OutOfBoundsException;
use PDO;
use Throwable;
use Xestify\repositories\PluginRepository;
use Xestify\repositories\PluginWriteRepository;

/**
 * Swaps a plugin's sort_order with its immediate neighbor in display order
 * (PluginRepository::listInstalled()'s ORDER BY sort_order ASC, slug ASC).
 * Unlike PluginStatusService's activate/deactivate, this does not invoke any
 * plugin lifecycle hook — reordering is not a status change.
 */
final class PluginOrderService
{
    public function __construct(
        private PDO $pdo,
        private PluginRepository $pluginRepository,
        private PluginWriteRepository $pluginWriteRepository
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function moveUp(string $slug): array
    {
        return $this->move($slug, -1);
    }

    /**
     * @return array<string, mixed>
     */
    public function moveDown(string $slug): array
    {
        return $this->move($slug, 1);
    }

    /**
     * @return array<string, mixed>
     */
    private function move(string $slug, int $direction): array
    {
        $this->pdo->beginTransaction();

        try {
            $current = $this->pluginRepository->lockBySlug($slug);
            if ($current === null) {
                throw new OutOfBoundsException("Plugin '{$slug}' is not installed.");
            }

            $pluginType = (string) ($current['manifest_json']['type'] ?? '');
            $neighbor = $this->pluginRepository->lockNeighborBySortOrder(
                (int) $current['sort_order'],
                $slug,
                $direction,
                $pluginType
            );

            if ($neighbor === null) {
                $this->pdo->commit();
                return $current;
            }

            $this->pluginWriteRepository->updateSortOrder($slug, (int) $neighbor['sort_order']);
            $this->pluginWriteRepository->updateSortOrder((string) $neighbor['slug'], (int) $current['sort_order']);

            $this->pdo->commit();

            $current['sort_order'] = $neighbor['sort_order'];
            return $current;
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $e;
        }
    }
}
