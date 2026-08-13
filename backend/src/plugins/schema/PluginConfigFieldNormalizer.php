<?php

declare(strict_types=1);

namespace Xestify\plugins\schema;

use InvalidArgumentException;

final class PluginConfigFieldNormalizer
{
    /**
     * @param array<int, mixed> $rows
     * @return array<int, array{active: bool, key: string, type: string, label: string, required: bool, summaryView: bool}>
     */
    public function normalizePayloadRows(array $rows): array
    {
        $normalized = [];
        foreach ($rows as $entry) {
            if (!is_array($entry)) {
                throw new InvalidArgumentException('Each field row must be an object.');
            }

            $field = $this->normalizeFieldDefinition($entry);
            $normalized[] = [
                'active' => (bool) ($entry['active'] ?? false),
                'key' => $field['key'],
                'type' => $field['type'],
                'label' => $field['label'],
                'required' => $field['required'],
                'summaryView' => (bool) ($entry['summaryView'] ?? true),
            ];
        }

        return $normalized;
    }

    /**
     * @param array<string, mixed> $entry
     * @return array{key: string, type: string, required: bool, label: string, summaryView: bool}
     */
    public function normalizeFieldDefinition(array $entry): array
    {
        $key = trim((string) ($entry['key'] ?? ''));
        if ($key === '') {
            throw new InvalidArgumentException('Field key is required.');
        }

        $type = trim((string) ($entry['type'] ?? 'string'));
        if ($type === '') {
            $type = 'string';
        }

        $allowedTypes = ['string', 'text', 'number', 'boolean', 'date', 'timestamp', 'email', 'select', 'uuid'];
        if (!in_array($type, $allowedTypes, true)) {
            throw new InvalidArgumentException("Unsupported field type '{$type}'.");
        }

        $label = trim((string) ($entry['label'] ?? ''));
        if ($label === '') {
            throw new InvalidArgumentException("Field '{$key}' label is required.");
        }

        return [
            'key' => $key,
            'type' => $type,
            'required' => (bool) ($entry['required'] ?? false),
            'label' => $label,
            'summaryView' => (bool) ($entry['summaryView'] ?? true),
        ];
    }

    /**
     * @param array<string, array<string, mixed>> $rowsByKey
     * @param array<string, mixed> $schema
     * @return array<int, array<string, mixed>>
     */
    public function orderedRows(array $rowsByKey, array $schema): array
    {
        $ordered = [];
        $order = isset($schema['ui_field_order']) && is_array($schema['ui_field_order'])
            ? $schema['ui_field_order']
            : [];

        foreach ($order as $candidate) {
            if (!is_string($candidate) || !isset($rowsByKey[$candidate])) {
                continue;
            }

            $ordered[] = $rowsByKey[$candidate];
            unset($rowsByKey[$candidate]);
        }

        foreach ($rowsByKey as $row) {
            $ordered[] = $row;
        }

        return $ordered;
    }
}
