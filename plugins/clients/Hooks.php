<?php

declare(strict_types=1);

namespace Xestify\plugins\clients;

use Xestify\plugins\contracts\AbstractUniqueFieldHook;

/**
 * Hooks for the clients plugin.
 *
 * Registers a beforeSave hook that enforces email uniqueness
 * across all records of the clients entity.
 */
final class Hooks extends AbstractUniqueFieldHook
{
    protected function entitySlug(): string
    {
        return 'clients';
    }

    protected function fieldName(): string
    {
        return 'email';
    }

    protected function duplicateMessage(string $value): string
    {
        return "El email '{$value}' ya está registrado en otro cliente.";
    }
}
