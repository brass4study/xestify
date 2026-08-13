<?php

declare(strict_types=1);

namespace Xestify\plugins\products;

use Xestify\plugins\contracts\AbstractEntityInstaller;

/**
 * Installer for the products plugin.
 *
 * Registers metadata for the "products" entity plugin and seeds its schema.
 */
final class Installer extends AbstractEntityInstaller
{
    protected function entitySlug(): string
    {
        return 'products';
    }

    protected function entityName(): string
    {
        return 'Productos';
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
