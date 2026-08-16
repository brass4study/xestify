<?php

declare(strict_types=1);

namespace Xestify\plugins\invoices;

use Xestify\plugins\contracts\AbstractUniqueFieldHook;

/**
 * Hooks for the invoices plugin.
 *
 * Registers a beforeSave hook that enforces invoice_number uniqueness
 * across all records of the invoices entity.
 */
final class Hooks extends AbstractUniqueFieldHook
{
    protected function pluginName(): string
    {
        return 'invoices';
    }

    protected function fieldName(): string
    {
        return 'invoice_number';
    }

    protected function duplicateMessage(string $value): string
    {
        return "El número de factura '{$value}' ya está registrado en otra factura.";
    }
}
