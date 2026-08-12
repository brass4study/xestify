<?php

declare(strict_types=1);

namespace Xestify\plugins\application;

use InvalidArgumentException;
use OutOfBoundsException;
use Xestify\repositories\PluginRepository;

final class ExtensionPluginConfigService
{
    public function __construct(
        private PluginRepository $pluginRepository,
        private PluginConfigFieldNormalizer $fieldNormalizer
    ) {
    }

    /**
     * @param array<string, mixed> $schema
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function saveConfig(string $slug, array $schema, array $payload): array
    {
        if (!isset($payload['fields']) || !is_array($payload['fields'])) {
            throw new InvalidArgumentException('fields must be an array.');
        }

        $rows = $this->fieldNormalizer->normalizePayloadRows($payload['fields']);
        $nextSchema = $schema;
        $nextSchema['fields'] = $this->buildNextFields($schema, $rows);
        $nextSchema['ui_field_order'] = array_keys($nextSchema['fields']);
        $nextSchema['target_entity'] = $this->resolveTargetEntity($payload, $schema);

        $updated = $this->pluginRepository->updateSchemaConfig($slug, $nextSchema);
        if ($updated === null) {
            throw new OutOfBoundsException("Plugin '{$slug}' was not found.");
        }

        return $updated;
    }

    /**
     * @param array<string, mixed> $schema
    * @return array{target_entity: string, fields: array<int, array<string, mixed>>}
     */
    public function buildConfigPayload(array $schema): array
    {
        $rowsByKey = [];
        $fieldDefinitions = isset($schema['fields']) && is_array($schema['fields'])
            ? $schema['fields']
            : [];

        foreach ($fieldDefinitions as $key => $definition) {
            if (!is_string($key) || !is_array($definition)) {
                continue;
            }

            $normalized = $this->fieldNormalizer->normalizeFieldDefinition([
                'key' => $key,
                'type' => $definition['type'] ?? 'string',
                'required' => $definition['required'] ?? false,
                'label' => $definition['label'] ?? $key,
            ]);
            $source = isset($definition['origin']) && is_string($definition['origin'])
                ? $definition['origin']
                : 'base';
            $editable = $source === 'additional' && (($definition['editable'] ?? true) !== false);

            $rowsByKey[$key] = [
                'active' => true,
                'key' => $normalized['key'],
                'type' => $normalized['type'],
                'label' => $normalized['label'],
                'required' => $normalized['required'],
                'summaryView' => $normalized['summaryView'] ?? true,
                'locked' => !$editable,
                'source' => $source,
            ];
        }

        $targetEntity = trim((string) ($schema['target_entity'] ?? '*'));
        if ($targetEntity === '') {
            $targetEntity = '*';
        }

        return [
            'target_entity' => $targetEntity,
            'fields' => $this->fieldNormalizer->orderedRows($rowsByKey, $schema),
        ];
    }

    /**
     * @param array<string, mixed> $schema
     * @param array<int, array{active: bool, key: string, type: string, label: string, required: bool, summaryView: bool}> $rows
     * @return array<string, array<string, mixed>>
     */
    private function buildNextFields(array $schema, array $rows): array
    {
        $existingFields = isset($schema['fields']) && is_array($schema['fields'])
            ? $schema['fields']
            : [];
        $nextFields = [];
        $seen = [];

        foreach ($rows as $row) {
            $key = $row['key'];
            if (isset($seen[$key])) {
                throw new InvalidArgumentException("Duplicated field key '{$key}'.");
            }

            $seen[$key] = true;
            if (!$row['active']) {
                continue;
            }

            $existingDefinition = isset($existingFields[$key]) && is_array($existingFields[$key])
                ? $existingFields[$key]
                : null;

            if ($this->isImmutableBaseField($existingDefinition)) {
                $this->assertImmutableBaseField($row, $existingDefinition, $key);
            }

            $nextFields[$key] = $this->mergeFieldDefinition($row, $existingDefinition);
        }

        if ($nextFields === []) {
            throw new InvalidArgumentException('At least one active field is required.');
        }

        return $nextFields;
    }

    /**
     * @param array{active: bool, key: string, type: string, label: string, required: bool, summaryView: bool} $row
     * @param array<string, mixed>|null $existingDefinition
     * @return array<string, mixed>
     */
    private function mergeFieldDefinition(array $row, ?array $existingDefinition): array
    {
        $nextField = [
            'type' => $row['type'],
            'required' => $row['required'],
            'label' => $row['label'],
            'summaryView' => $row['summaryView'],
        ];

        if ($existingDefinition === null) {
            $nextField['origin'] = 'additional';
            return $nextField;
        }

        foreach ($existingDefinition as $metaKey => $metaValue) {
            if (in_array($metaKey, ['type', 'required', 'label'], true)) {
                continue;
            }

            $nextField[$metaKey] = $metaValue;
        }

        return $nextField;
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $schema
     */
    private function resolveTargetEntity(array $payload, array $schema): string
    {
        $targetEntity = trim((string) ($payload['target_entity'] ?? ($schema['target_entity'] ?? '*')));
        if ($targetEntity === '') {
            throw new InvalidArgumentException('target_entity is required.');
        }

        if ($targetEntity !== '*' && !preg_match('/^[a-z][a-z0-9_]*$/', $targetEntity)) {
            throw new InvalidArgumentException('target_entity must be "*" or a valid entity slug.');
        }

        if ($targetEntity !== '*') {
            $activeEntitySlugs = $this->pluginRepository->listActiveEntitySlugs();
            if (!in_array($targetEntity, $activeEntitySlugs, true)) {
                throw new InvalidArgumentException("target_entity '{$targetEntity}' is not an active entity slug.");
            }
        }

        return $targetEntity;
    }

    /**
     * @param array<string, mixed>|null $definition
     */
    private function isImmutableBaseField(?array $definition): bool
    {
        if ($definition === null) {
            return false;
        }

        return (string) ($definition['origin'] ?? 'base') !== 'additional';
    }

    /**
     * @param array{active: bool, key: string, type: string, label: string, required: bool, summaryView: bool} $row
     * @param array<string, mixed> $definition
     */
    private function assertImmutableBaseField(array $row, array $definition, string $key): void
    {
        $expectedType = trim((string) ($definition['type'] ?? 'string'));
        if ($expectedType === '') {
            $expectedType = 'string';
        }

        $expectedLabel = trim((string) ($definition['label'] ?? $key));
        if ($expectedLabel === '') {
            $expectedLabel = $key;
        }

        $isInvalid = !$row['active']
            || $row['type'] !== $expectedType
            || $row['label'] !== $expectedLabel
            || $row['required'] !== (bool) ($definition['required'] ?? false);

        if ($isInvalid) {
            throw new InvalidArgumentException("Base field '{$key}' cannot be edited or deactivated.");
        }
    }
}

