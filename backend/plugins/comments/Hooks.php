<?php

declare(strict_types=1);

namespace Xestify\plugins\comments;

use Xestify\plugins\HookDispatcher;

/**
 * Hooks for the comments plugin.
 *
 * Registers a registerTabs filter hook that injects a "Comentarios"
 * tab into every entity view.
 */
final class Hooks
{
    /**
     * Register all hooks for this plugin on the given dispatcher.
     */
    public function register(HookDispatcher $dispatcher): void
    {
        $dispatcher->register(
            'registerTabs',
            static function (array $tabs, array $args): array {
                $tabs[] = [
                    'id'       => 'comments',
                    'label'    => 'Comentarios',
                    'icon'     => 'fa-comments',
                    'endpoint' => '/api/v1/plugins/comments/' . ($args['entity'] ?? '') . '/{id}',
                ];
                return $tabs;
            },
            priority: 50
        );
    }
}
