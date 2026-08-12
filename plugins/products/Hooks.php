<?php

declare(strict_types=1);

namespace Xestify\plugins\products;

use Xestify\plugins\AbstractUniqueFieldHook;

/**
 * Hooks for the products plugin.
 *
 * Registers a beforeSave hook that enforces SKU uniqueness
 * across all records of the products entity.
 */
final class Hooks extends AbstractUniqueFieldHook
{
    protected function entitySlug(): string
    {
        return 'products';
    }

    protected function fieldName(): string
    {
        return 'sku';
    }

    protected function duplicateMessage(string $value): string
    {
        return "El SKU '{$value}' ya esta registrado en otro producto.";
    }
}
