<?php

declare(strict_types=1);

namespace Xestify\plugins\clients;

use Xestify\plugins\contracts\AbstractEntityInstaller;

/**
 * Installer for the clients plugin.
 *
 * Registers metadata for the "clients" entity plugin and seeds its schema.
 */
final class Installer extends AbstractEntityInstaller
{
    protected function entitySlug(): string
    {
        return 'clients';
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
