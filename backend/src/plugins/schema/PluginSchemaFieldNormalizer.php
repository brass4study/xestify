<?php

declare(strict_types=1);

namespace Xestify\plugins\schema;

final class PluginSchemaFieldNormalizer
{
    /**
     * @param mixed $rawFields
     * @return array<string, array{type: string, required: bool, label: string}>|null
     */
    public static function normalize(mixed $rawFields): ?array
    {
        if (!is_array($rawFields)) {
            return null;
        }

        $normalized = [];

        if (array_is_list($rawFields)) {
            foreach ($rawFields as $entry) {
                if (!is_array($entry)) {
                    continue;
                }

                $key = trim((string) ($entry['name'] ?? ($entry['key'] ?? '')));
                if ($key === '') {
                    continue;
                }

                $normalized[$key] = [
                    ...$entry,
                    'type' => trim((string) ($entry['type'] ?? 'string')),
                    'required' => (bool) ($entry['required'] ?? false),
                    'label' => trim((string) ($entry['label'] ?? $key)),
                ];
            }

            return $normalized;
        }

        foreach ($rawFields as $key => $definition) {
            if (!is_string($key) || !is_array($definition)) {
                continue;
            }

            $normalized[$key] = [
                ...$definition,
                'type' => trim((string) ($definition['type'] ?? 'string')),
                'required' => (bool) ($definition['required'] ?? false),
                'label' => trim((string) ($definition['label'] ?? $key)),
            ];
        }

        return $normalized;
    }
}
