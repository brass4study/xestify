<?php

declare(strict_types=1);

namespace Xestify\plugins\products;

use PDO;
use Xestify\exceptions\HookException;
use Xestify\plugins\HookDispatcher;

/**
 * Hooks for the products plugin.
 *
 * Registers a beforeSave hook that enforces SKU uniqueness
 * across all records of the products entity.
 */
final class Hooks
{
    private const ENTITY_SLUG = 'products';

    public function __construct(private PDO $pdo)
    {
    }

    public function register(HookDispatcher $dispatcher): void
    {
        $dispatcher->register(
            'beforeSave',
            fn(array $ctx): array => $this->enforceSkuUniqueness($ctx),
            priority: 5
        );
    }

    /**
     * @param  array<string, mixed> $ctx
     * @return array<string, mixed>
     * @throws HookException when a duplicate SKU is found
     */
    private function enforceSkuUniqueness(array $ctx): array
    {
        if (($ctx['slug'] ?? '') !== self::ENTITY_SLUG) {
            return $ctx;
        }

        $sku = (string) ($ctx['data']['sku'] ?? '');
        if ($sku === '') {
            return $ctx;
        }

        $recordId = (string) ($ctx['id'] ?? ($ctx['data']['id'] ?? ''));

        $sql = 'SELECT COUNT(*) FROM plugin_entity_data
                WHERE entity_slug = :slug
                  AND content->>\'sku\' = :sku
                  AND deleted_at IS NULL'
             . ($recordId !== '' ? ' AND id <> :id' : '');

        $stmt = $this->pdo->prepare($sql);
        $params = [':slug' => self::ENTITY_SLUG, ':sku' => $sku];

        if ($recordId !== '') {
            $params[':id'] = $recordId;
        }

        $stmt->execute($params);
        $count = (int) $stmt->fetchColumn();

        if ($count > 0) {
            throw new HookException("El SKU '{$sku}' ya esta registrado en otro producto.");
        }

        return $ctx;
    }
}

