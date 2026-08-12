<?php

declare(strict_types=1);

namespace Xestify\plugins\application;

/**
 * Recursive, key-order-independent normalization of schema field/section
 * definitions for equality comparison. Shared by PluginSchemaMergeService
 * (additive-update diffing) and InstalledPluginSchemaValidator (installed
 * vs. canonical/target containment checks) — both need "same definition,
 * different key order" to compare as equal.
 */
final class SchemaComparisonUtil
{
    public function normalize(mixed $value): mixed
    {
        if (!is_array($value)) {
            return $value;
        }

        if (array_is_list($value)) {
            return array_map(fn(mixed $item): mixed => $this->normalize($item), $value);
        }

        ksort($value);
        foreach ($value as $key => $item) {
            $value[$key] = $this->normalize($item);
        }

        return $value;
    }
}
