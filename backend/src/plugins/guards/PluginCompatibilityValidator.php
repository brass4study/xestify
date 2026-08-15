<?php

declare(strict_types=1);

namespace Xestify\plugins\guards;

use Xestify\exceptions\PluginException;

final class PluginCompatibilityValidator
{
    public const CORE_VERSION = '1.0.0';

    /**
     * @param array<string, mixed> $manifest
     */
    public function validate(array $manifest): void
    {
        if (version_compare((string) $manifest['core_version'], self::CORE_VERSION, '>')) {
            throw new PluginException(
                "Plugin '{$manifest['name']}' requires core >= {$manifest['core_version']}, "
                . 'current core is ' . self::CORE_VERSION
            );
        }
    }
}
