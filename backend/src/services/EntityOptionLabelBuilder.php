<?php

declare(strict_types=1);

namespace Xestify\services;

/**
 * Pure logic that derives the {id, label} display string for an entity
 * record from its schema and content, used by EntityService::listOptions()
 * to feed relation <select> pickers declared on other entities'
 * schema.relations[].target_entity. No I/O — callers own fetching the
 * schema and the record content.
 *
 * Mirrors frontend/src/js/models/EntityRecordModel.js's recordSummaryLabel()
 * so a record's label matches both in its own breadcrumb and when it
 * appears as a relation option on another entity.
 */
final class EntityOptionLabelBuilder
{
    /**
     * @param array<string, mixed> $content
     * @param array<string, mixed> $schema
     */
    public function build(array $content, array $schema, string $fallbackId): string
    {
        [$keys, $definitions] = $this->summaryKeysToDisplay($schema);

        $parts = [];
        foreach ($keys as $key) {
            $displayValue = $this->summaryFieldDisplayValue($content[$key] ?? null, $definitions[$key] ?? []);
            if ($displayValue === null) {
                continue;
            }
            $trimmed = trim($displayValue);
            if ($trimmed !== '') {
                $parts[] = $trimmed;
            }
        }

        return $parts === [] ? $fallbackId : implode(' ', $parts);
    }

    /**
     * Keys to display in the record summary label, in priority order:
     * type+name+surnames, then type+name+description, then the first two
     * summaryView fields in display order.
     *
     * @param array<string, mixed> $schema
     * @return array{0: array<int, string>, 1: array<string, array<string, mixed>>}
     */
    private function summaryKeysToDisplay(array $schema): array
    {
        $definitions = $this->summaryFieldDefinitions($schema);

        foreach ([['type', 'name', 'surnames'], ['type', 'name', 'description']] as $combo) {
            if (isset($definitions[$combo[0]], $definitions[$combo[1]], $definitions[$combo[2]])) {
                return [$combo, $definitions];
            }
        }

        return [array_slice($this->orderedSummaryKeys($schema, $definitions), 0, 2), $definitions];
    }

    private function isValidFieldKey(mixed $key): bool
    {
        return is_string($key) && trim($key) !== '';
    }

    /**
     * @param array<string, array<string, mixed>> $definitions
     */
    private function collectFieldsDefinitions(mixed $fields, array &$definitions): void
    {
        if (!is_array($fields)) {
            return;
        }

        foreach ($fields as $key => $definition) {
            if ($this->isValidFieldKey($key) && is_array($definition)) {
                $definitions[$key] = $definition;
            }
        }
    }

    /**
     * @param array<string, array<string, mixed>> $definitions
     */
    private function collectCustomFieldsDefinitions(mixed $customFields, array &$definitions): void
    {
        if (!is_array($customFields)) {
            return;
        }

        foreach ($customFields as $definition) {
            if (!is_array($definition)) {
                continue;
            }

            $key = $definition['key'] ?? null;
            if ($this->isValidFieldKey($key)) {
                $definitions[$key] = $definition;
            }
        }
    }

    /**
     * {key => definition} map combining fields (object) then custom_fields
     * (array), in declaration order. Mirrors PluginConfigFieldNormalizer's
     * `(bool) ($entry['summaryView'] ?? true)` default. Deliberately does
     * NOT reuse SchemaFieldExtractor::extract(): that merges in
     * `identities` too and loses the summaryView flag. Keeps the full
     * definition (not just the name) because summaryFieldDisplayValue()
     * needs `type`/`options` to resolve `select` values to their label.
     *
     * @param array<string, mixed> $schema
     * @return array<string, array<string, mixed>>
     */
    private function summaryFieldDefinitions(array $schema): array
    {
        $definitions = [];
        $this->collectFieldsDefinitions($schema['fields'] ?? null, $definitions);
        $this->collectCustomFieldsDefinitions($schema['custom_fields'] ?? null, $definitions);

        return $definitions;
    }

    /**
     * Declaration-order keys with summaryView !== false, reordered by
     * schema.ui_field_order the same way DynamicTable.normalizeColumns()
     * reorders table columns on the frontend (frontend/src/js/views/
     * modules/DynamicTable.js): entries named in ui_field_order come
     * first, in that order; everything else keeps declaration order.
     *
     * @param array<string, mixed> $schema
     * @param array<string, array<string, mixed>> $definitions
     * @return array<int, string>
     */
    private function orderedSummaryKeys(array $schema, array $definitions): array
    {
        $declarationOrder = [];
        foreach ($definitions as $key => $definition) {
            if (($definition['summaryView'] ?? true) !== false) {
                $declarationOrder[] = $key;
            }
        }

        $uiFieldOrder = isset($schema['ui_field_order']) && is_array($schema['ui_field_order']) ? $schema['ui_field_order'] : [];
        $remaining = array_flip($declarationOrder);
        $ordered = [];
        foreach ($uiFieldOrder as $candidate) {
            if (is_string($candidate) && isset($remaining[$candidate])) {
                $ordered[] = $candidate;
                unset($remaining[$candidate]);
            }
        }
        foreach ($declarationOrder as $key) {
            if (isset($remaining[$key])) {
                $ordered[] = $key;
            }
        }

        return $ordered;
    }

    /**
     * Resolves a raw content value to its display string, mapping `select`
     * values through their declared options via matchSelectOptionLabel().
     *
     * @param array<string, mixed> $definition
     */
    private function summaryFieldDisplayValue(mixed $rawValue, array $definition): ?string
    {
        if (!is_scalar($rawValue)) {
            return null;
        }

        if (($definition['type'] ?? null) === 'select' && is_array($definition['options'] ?? null)) {
            $label = $this->matchSelectOptionLabel($definition['options'], $rawValue);
            if ($label !== null) {
                return $label;
            }
        }

        return (string) $rawValue;
    }

    /**
     * Matches $rawValue against a `select` field's declared options (value ->
     * label) the same way SelectFieldValidator matches option values — each
     * option may be a {value, label} pair or a bare scalar (legacy
     * schema.json catalogs). Returns null when no option matches.
     */
    private function matchSelectOptionLabel(array $options, mixed $rawValue): ?string
    {
        foreach ($options as $option) {
            $optionValue = is_array($option) ? ($option['value'] ?? null) : $option;
            if ($optionValue !== null && (string) $optionValue === (string) $rawValue) {
                return (string) (is_array($option) ? ($option['label'] ?? $option['value'] ?? '') : $option);
            }
        }

        return null;
    }
}
