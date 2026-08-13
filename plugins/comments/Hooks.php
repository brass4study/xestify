<?php

declare(strict_types=1);

namespace Xestify\plugins\comments;

use PDO;
use Xestify\core\HookDispatcher;

/**
 * Hooks for the comments plugin.
 *
 * Registers a registerTabs filter hook that injects a "Comentarios"
 * tab only for entities allowed by the plugin configuration stored in DB.
 */
final class Hooks
{
    public function __construct(private ?PDO $pdo = null)
    {
    }

    /**
     * Register all hooks for this plugin on the given dispatcher.
     */
    public function register(HookDispatcher $dispatcher): void
    {
        $dispatcher->register(
            'registerTabs',
            function (array $tabs, array $args): array {
                $entity = trim((string) ($args['entity'] ?? ''));
                $tabList = $tabs;
                $shouldAddTab = $entity !== '' && $this->isEntityAllowed($entity);

                if ($shouldAddTab) {
                    $tabList[] = [
                        'id'       => 'comments',
                        'label'    => 'Comentarios',
                        'icon'     => 'fa-comments',
                        'endpoint' => '/plugins/comments/' . $entity . '/{id}',
                    ];
                }

                return $tabList;
            },
            priority: 50
        );
    }

    private function isEntityAllowed(string $entity): bool
    {
        if ($this->pdo === null) {
            return true;
        }

        $stmt = $this->pdo->prepare(
            "SELECT status, schema_json
               FROM plugins
              WHERE slug = :slug
                AND plugin_type = 'extension'
              LIMIT 1"
        );
        $stmt->execute([':slug' => 'comments']);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        $isAllowed = false;
        if ($row !== false && (string) ($row['status'] ?? '') === 'active') {
            $decoded = json_decode((string) ($row['schema_json'] ?? ''), true);
            if (!is_array($decoded)) {
                $isAllowed = true;
            } else {
                $target = trim((string) ($decoded['target_entity'] ?? '*'));
                $isAllowed = $target === '' || $target === '*' || $target === $entity;
            }
        }

        return $isAllowed;
    }
}
