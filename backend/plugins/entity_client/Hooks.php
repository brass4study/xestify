<?php

declare(strict_types=1);

namespace Xestify\plugins\entity_client;

use PDO;
use Xestify\exceptions\HookException;
use Xestify\plugins\HookDispatcher;

/**
 * Hooks for the entity_client plugin.
 *
 * Registers a beforeSave hook that enforces email uniqueness
 * across all client records.
 */
final class Hooks
{
    private const ENTITY_SLUG = 'client';

    public function __construct(private PDO $pdo)
    {
    }

    /**
     * Register all hooks for this plugin on the given dispatcher.
     */
    public function register(HookDispatcher $dispatcher): void
    {
        $dispatcher->register(
            'beforeSave',
            fn(array $ctx): array => $this->enforceEmailUniqueness($ctx),
            priority: 5
        );
    }

    /**
     * Enforce that no other active client record has the same email.
     *
     * @param  array<string, mixed> $ctx
     * @return array<string, mixed>
     * @throws HookException when a duplicate email is found
     */
    private function enforceEmailUniqueness(array $ctx): array
    {
        if (($ctx['slug'] ?? '') !== self::ENTITY_SLUG) {
            return $ctx;
        }

        $email = (string) ($ctx['data']['email'] ?? '');

        if ($email === '') {
            return $ctx;
        }

        $recordId = (string) ($ctx['data']['id'] ?? '');

        $sql = 'SELECT COUNT(*) FROM entity_data
                WHERE entity_slug = :slug
                  AND content->>\'email\' = :email
                  AND deleted_at IS NULL'
             . ($recordId !== '' ? ' AND id != :id' : '');

        $stmt = $this->pdo->prepare($sql);
        $params = [':slug' => self::ENTITY_SLUG, ':email' => $email];

        if ($recordId !== '') {
            $params[':id'] = $recordId;
        }

        $stmt->execute($params);
        $count = (int) $stmt->fetchColumn();

        if ($count > 0) {
            throw new HookException("El email '{$email}' ya está registrado en otro cliente.");
        }

        return $ctx;
    }
}

