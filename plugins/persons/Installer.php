<?php

declare(strict_types=1);

namespace Xestify\plugins\persons;

use Xestify\plugins\contracts\AbstractEntityInstaller;

/**
 * Installer for the persons plugin.
 *
 * Registers metadata for the "persons" entity plugin and seeds its schema.
 */
final class Installer extends AbstractEntityInstaller
{
    protected function entitySlug(): string
    {
        return 'persons';
    }

    protected function entityName(): string
    {
        return 'Clientes';
    }

    protected function schemaVersion(): int
    {
        return 1;
    }

    protected function schemaPath(): string
    {
        return __DIR__ . '/schema.json';
    }
}
