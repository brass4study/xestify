<?php

declare(strict_types=1);

namespace Xestify\plugins\schema;

use InvalidArgumentException;
use Xestify\repositories\PluginRepository;

final class ExtensionPluginConfigService
{
    public function __construct(
        private PluginRepository $pluginRepository,
        private PluginConfigFieldNormalizer $fieldNormalizer,
        private RelationsPayloadCompiler $relationsCompiler
    ) {
    }

    /**
     * Computes the next schema (fields + relations — target_entity lives in
     * manifest_json, not schema_json, STORY 10.3 §2bis) and the resolved
     * target_entity. Does not persist: the caller
     * (PluginAdministrationService::saveConfig()) writes both in a single
     * PluginRepository::updateExtensionConfig() call, since they touch two
     * different JSONB columns of the same row.
     *
     * Relations are editable here exactly like entity relations (STORY
     * 10.5) — validated by the same RelationsPayloadCompiler, so an
     * extension plugin's belongs_to relations aren't permanently fixed to
     * whatever the plugin author wrote in schema.json on disk.
     *
     * @param array<string, mixed> $schema
     * @param array<string, mixed> $payload
     * @return array{schema: array<string, mixed>, target_entity: string}
     */
    public function saveConfig(array $schema, string $currentTargetEntity, array $payload): array
    {
        if (!isset($payload['fields']) || !is_array($payload['fields'])) {
            throw new InvalidArgumentException('fields must be an array.');
        }

        $rows = $this->fieldNormalizer->normalizePayloadRows($payload['fields']);
        $nextFields = $this->buildNextFields($schema, $rows);
        $nextSchema = $schema;
        $nextSchema['fields'] = $nextFields;
        $nextSchema['ui_field_order'] = array_keys($nextFields);

        if (array_key_exists('relations', $payload) && !is_array($payload['relations'])) {
            throw new InvalidArgumentException('relations must be an array.');
        }
        $relationsPayload = is_array($payload['relations'] ?? null) ? $payload['relations'] : [];
        $fieldKeys = array_fill_keys(array_keys($nextFields), true);
        $nextSchema['relations'] = $this->relationsCompiler->compile($relationsPayload, $fieldKeys);

        return [
            'schema' => $nextSchema,
            'target_entity' => $this->resolveTargetEntity($payload, $currentTargetEntity),
        ];
    }

    /**
     * @param array<string, mixed> $schema
     * @param array<int, mixed> $layers manifest_json.layers (STORY 10.5) — NOT
     *   part of schema_json: it's a fixed, never-editable-from-PluginConfig
     *   catalog, so it lives alongside target_entity in manifest_json rather
     *   than in the mutable fields/relations/ui_field_order shape schema_json
     *   represents. Caller (PluginConfigService::buildConfigResponse()/
     *   buildAvailablePluginPreview()) extracts it from the manifest.
    * @return array{target_entity: string, fields: array<int, array<string, mixed>>}
     */
    public function buildConfigPayload(array $schema, string $targetEntity, array $layers = []): array
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
                'options' => $definition['options'] ?? null,
                'resortable' => $definition['resortable'] ?? true,
                'layer' => $definition['layer'] ?? 'general',
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
                'resortable' => $normalized['resortable'] ?? true,
                'layer' => $normalized['layer'] ?? 'general',
            ];
            if (isset($normalized['options'])) {
                $rowsByKey[$key]['options'] = $normalized['options'];
            }
        }

        $targetEntity = trim($targetEntity);
        if ($targetEntity === '') {
            $targetEntity = '*';
        }

        return [
            'target_entity' => $targetEntity,
            'fields' => $this->fieldNormalizer->orderedRows($rowsByKey, $schema),
            'relations' => $this->relationsFromSchema($schema),
            'layers' => $this->normalizeLayers($layers),
        ];
    }

    /**
     * Read-only relations projection for PluginConfig's "Relaciones" panel
     * — relations ARE editable from PluginConfig (STORY 10.5), this method
     * just decodes what's currently on schema_json for the GET response;
     * the write side is RelationsPayloadCompiler::compile(). `layer`
     * (od/os/general, renamed from `group`) is a named UI zone with no
     * entity equivalent — it tells the plugin's own plugin.js/
     * PluginItemEdit.js where to render the relation's select, not
     * something PluginConfig itself interprets.
     *
     * @param array<string, mixed> $schema
     * @return array<int, array{key: string, type: string, target_entity: string, target_field: string, label: string, required: bool, layer: string}>
     */
    private function relationsFromSchema(array $schema): array
    {
        if (!isset($schema['relations']) || !is_array($schema['relations'])) {
            return [];
        }

        $relations = [];
        foreach ($schema['relations'] as $entry) {
            if (!is_array($entry) || !is_string($entry['key'] ?? null) || $entry['key'] === '') {
                continue;
            }

            $relations[] = [
                'key' => $entry['key'],
                'type' => 'belongs_to',
                'target_entity' => (string) ($entry['target_entity'] ?? ''),
                'target_field' => (string) ($entry['target_field'] ?? ''),
                'label' => (string) ($entry['label'] ?? $entry['key']),
                'required' => (bool) ($entry['required'] ?? false),
                'layer' => (string) ($entry['layer'] ?? 'general'),
            ];
        }

        return $relations;
    }

    /**
     * Read-only catalog of named UI zones a plugin declares in
     * manifest_json.layers (STORY 10.5) — fixed on disk by the plugin
     * author ("la plantilla"), never editable from PluginConfig; only used
     * to populate the "Capa" select's options. Permissive projection (skips
     * malformed entries), same style as relationsFromSchema().
     *
     * @param array<int, mixed> $layers
     * @return array<int, array{key: string, label: string}>
     */
    private function normalizeLayers(array $layers): array
    {
        $normalized = [];
        foreach ($layers as $entry) {
            if (!is_array($entry) || !is_string($entry['key'] ?? null) || $entry['key'] === '') {
                continue;
            }

            $normalized[] = [
                'key' => $entry['key'],
                'label' => (string) ($entry['label'] ?? $entry['key']),
            ];
        }

        return $normalized;
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

            $this->assertLayerRespectsResortable($row, $existingDefinition, $key);

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
            // Unlike resortable/min/max (author-locked, passed through
            // verbatim below), layer is admin-editable from PluginConfig's
            // "Capa" column (STORY 10.5) — sourced from the payload row,
            // not carried over from disk. assertLayerRespectsResortable()
            // already rejected a changed value here when resortable=false.
            'layer' => $row['layer'],
        ];

        if ($row['type'] === 'select' && isset($row['options'])) {
            $nextField['options'] = $row['options'];
        }

        if ($existingDefinition === null) {
            $nextField['origin'] = 'additional';
            return $nextField;
        }

        foreach ($existingDefinition as $metaKey => $metaValue) {
            if (in_array($metaKey, ['type', 'required', 'label', 'options', 'layer'], true)) {
                continue;
            }

            $nextField[$metaKey] = $metaValue;
        }

        return $nextField;
    }

    /**
     * A field with `resortable: false` has both its display order AND its
     * UI zone hardcoded by the plugin's own hand-written rendering (STORY
     * 10.5 "layers" convention) — reassigning its layer from PluginConfig
     * would be exactly as misleading as reordering it, which resortable
     * already prevents via the hidden Subir/Bajar buttons. This closes the
     * same gap server-side, independent of origin/locked, so a crafted API
     * request can't bypass what the frontend's disabled "Capa" select
     * already prevents.
     *
     * @param array{active: bool, key: string, type: string, label: string, required: bool, summaryView: bool, layer: string} $row
     * @param array<string, mixed>|null $existingDefinition
     */
    private function assertLayerRespectsResortable(array $row, ?array $existingDefinition, string $key): void
    {
        if ($existingDefinition === null) {
            return;
        }

        $resortable = ($existingDefinition['resortable'] ?? true) !== false;
        if ($resortable) {
            return;
        }

        $existingLayer = (string) ($existingDefinition['layer'] ?? 'general');
        if ($row['layer'] !== $existingLayer) {
            throw new InvalidArgumentException("Field '{$key}' is not resortable, its layer cannot be reassigned.");
        }
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function resolveTargetEntity(array $payload, string $currentTargetEntity): string
    {
        $targetEntity = trim((string) ($payload['target_entity'] ?? ($currentTargetEntity !== '' ? $currentTargetEntity : '*')));
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
