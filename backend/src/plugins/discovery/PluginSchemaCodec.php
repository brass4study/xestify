<?php

declare(strict_types=1);

namespace Xestify\plugins\discovery;

use Xestify\exceptions\PluginException;

final class PluginSchemaCodec
{
    public function decode(mixed $schema): ?array
    {
        if ($schema === null || $schema === '') {
            return null;
        }

        if (is_array($schema)) {
            return $schema;
        }

        $decoded = is_string($schema) ? json_decode($schema, true) : null;

        return is_array($decoded) ? $decoded : null;
    }

    public function encode(?array $schema, string $slug): ?string
    {
        if ($schema === null) {
            return null;
        }

        $json = json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new PluginException("Cannot encode schema for plugin '{$slug}'.");
        }

        return $json;
    }
}
