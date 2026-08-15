<?php

declare(strict_types=1);

namespace Xestify\plugins\comments;

use PDO;
use Xestify\core\HookDispatcher;

/**
 * Hooks for the comments plugin.
 *
 * Registers a registerTabs filter hook that injects a "Comentarios" tab for
 * every ACTIVE instance of this plugin (plugin_name='comments') whose own
 * target_entity configuration allows the entity currently being viewed.
 *
 * plugin_name is not unique (STORY 10.3): several independent `comments`
 * instances can be active at once (different slug, different target_entity
 * each), so this queries by plugin_name and contributes one tab per matching
 * instance, keyed by that instance's own (unique) slug — never a hardcoded
 * literal, which would silently stop matching the moment an admin renames
 * this plugin's slug, or would only ever reflect one arbitrary instance
 * once a second one exists.
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

                if ($entity === '') {
                    return $tabList;
                }

                foreach ($this->allowedInstances($entity) as $slug) {
                    $tabList[] = [
                        'id'          => $slug,
                        'label'       => 'Comentarios',
                        'icon'        => 'fa-comments',
                        'endpoint'    => '/plugins/' . $slug . '/' . $entity . '/{id}',
                        'plugin_name' => 'comments',
                    ];
                }

                return $tabList;
            },
            priority: 50
        );
    }

    /**
     * @return list<string> slugs of active `comments` instances whose
     *   target_entity configuration allows $entity
     */
    private function allowedInstances(string $entity): array
    {
        if ($this->pdo === null) {
            return [];
        }

        $stmt = $this->pdo->prepare(
            "SELECT slug, manifest_json
               FROM plugins
              WHERE manifest_json->>'name' = 'comments'
                AND manifest_json->>'type' = 'extension'
                AND status = 'active'"
        );
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $allowed = [];
        foreach ($rows as $row) {
            $slug = (string) ($row['slug'] ?? '');
            if ($slug === '') {
                continue;
            }

            $decoded = json_decode((string) ($row['manifest_json'] ?? ''), true);
            $target = is_array($decoded) ? trim((string) ($decoded['target_entity'] ?? '*')) : '*';

            if ($target === '' || $target === '*' || $target === $entity) {
                $allowed[] = $slug;
            }
        }

        return $allowed;
    }
}
