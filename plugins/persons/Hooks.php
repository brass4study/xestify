<?php

declare(strict_types=1);

namespace Xestify\plugins\persons;

use Xestify\plugins\contracts\AbstractUniqueFieldHook;

/**
 * Hooks for the persons plugin.
 *
 * Registers a beforeSave hook that enforces email uniqueness
 * across all records of the persons entity.
 */
final class Hooks extends AbstractUniqueFieldHook
{
    protected function pluginName(): string
    {
        return 'persons';
    }

    protected function fieldName(): string
    {
        return 'mail';
    }

    protected function duplicateMessage(string $value): string
    {
        return "El email '{$value}' ya está registrado en otro cliente.";
    }
}
