<?php

declare(strict_types=1);

namespace Xestify\plugins\schema;

use InvalidArgumentException;
use OutOfBoundsException;
use Throwable;
use Xestify\plugins\discovery\PluginSourceService;
use Xestify\repositories\PluginRepository;
use Xestify\repositories\PluginWriteRepository;

/**
 * Transforms plugin schema_json (raw fields/custom_fields/relations) into
 * and out of the UI-facing "rows" shape PluginConfig's Campos table uses, and
 * persists it. Split out of PluginAdministrationService (STORY 10.3 §2bis
 * cleanup — that class exceeded the method-count threshold); orchestration
 * (locking rows, transactions, identity edits) stays there, this class only
 * knows about the fields/target_entity shape itself.
 */
final class PluginConfigService
{
    public function __construct(
        private PluginWriteRepository $pluginWriteRepository,
        private ExtensionPluginConfigService $extensionPluginConfigService,
        private PluginConfigFieldNormalizer $fieldNormalizer,
        private PluginSourceService $pluginSourceService,
        private PluginRepository $pluginRepository
    ) {
    }

    /**
     * Validates and persists fields/target_entity for a locked plugin row —
     * shared by PluginAdministrationService::saveConfig() (an already-active,
     * already-installed plugin) and ::registerNew() (a freshly inserted row,
     * activated right before this runs: initial config at creation time,
     * STORY 10.3). Never checks plugin status itself — that's deliberately
     * the caller's job (saveConfig() enforces active-only before this runs);
     * this method only cares about the fields/target_entity shape.
     *
     * @param array<string, mixed> $plugin locked/decoded row (manifest_json is a PHP array)
     * @param array<string, mixed> $payload
     * @return array<string, mixed> the updated row
     */
    public function applyConfigPayload(array $plugin, array $payload): array
    {
        $slug = (string) $plugin['slug'];
        $currentSchema = $this->decodePluginSchema($plugin);

        if ((string) ($plugin['manifest_json']['type'] ?? '') === 'extension') {
            $currentTargetEntity = (string) ($plugin['manifest_json']['target_entity'] ?? '*');
            $result = $this->extensionPluginConfigService->saveConfig($currentSchema, $currentTargetEntity, $payload);

            $updated = $this->pluginWriteRepository->updateExtensionConfig($slug, $result['schema'], $result['target_entity']);
            if ($updated === null) {
                throw new OutOfBoundsException("Plugin '{$slug}' was not found.");
            }

            return $updated;
        }

        if (!isset($payload['fields']) || !is_array($payload['fields'])) {
            throw new InvalidArgumentException('fields must be an array.');
        }

        $baseFields = $this->baseFieldsFromSchema($currentSchema);
        $baseByKey = [];
        foreach ($baseFields as $field) {
            $baseByKey[$field['key']] = $field;
        }

        $catalog = $this->suggestedCatalogFromSchema($currentSchema);
        $catalogByKey = [];
        foreach ($catalog as $field) {
            $catalogByKey[$field['key']] = $field;
        }

        $fields = $this->fieldNormalizer->normalizePayloadRows($payload['fields']);
        $compiled = $this->compileEntityConfigRows($fields, $baseByKey, $catalogByKey);

        foreach ($baseFields as $base) {
            if (!isset($compiled['seen_keys'][$base['key']])) {
                throw new InvalidArgumentException("Base field '{$base['key']}' is missing from payload.");
            }
        }

        if (array_key_exists('relations', $payload) && !is_array($payload['relations'])) {
            throw new InvalidArgumentException('relations must be an array.');
        }
        $relationsPayload = is_array($payload['relations'] ?? null) ? $payload['relations'] : [];
        $relations = $this->compileRelationsPayload($relationsPayload, $compiled['seen_keys']);

        $nextSchema = $currentSchema;
        $nextSchema['plugin_suggested_custom_fields'] = $compiled['suggested_catalog'];
        $nextSchema['custom_fields'] = $compiled['active_custom_fields'];
        $nextSchema['ui_field_order'] = $compiled['ui_order'];
        $nextSchema['relations'] = $relations;

        $updated = $this->pluginWriteRepository->updateSchemaConfig($slug, $nextSchema);
        if ($updated === null) {
            throw new OutOfBoundsException("Plugin '{$slug}' was not found.");
        }

        return $updated;
    }

    /**
     * @param array<string, mixed> $plugin
     * @return array{plugin: array<string, mixed>, config: array<string, mixed>}
     */
    public function buildConfigResponse(array $plugin): array
    {
        $schema = $this->decodePluginSchema($plugin);
        $manifest = is_array($plugin['manifest_json'] ?? null) ? $plugin['manifest_json'] : [];
        $pluginType = (string) ($manifest['type'] ?? '');

        if ($pluginType === 'extension') {
            $targetEntity = (string) ($manifest['target_entity'] ?? '*');
            $configPayload = $this->extensionPluginConfigService->buildConfigPayload($schema, $targetEntity);
        } else {
            $configPayload = $this->buildEntityConfigPayload($schema);
        }

        return [
            'plugin' => [
                'slug' => (string) ($plugin['slug'] ?? ''),
                'name' => (string) ($manifest['label'] ?? ''),
                'description' => (string) ($manifest['description'] ?? ''),
                'plugin_type' => $pluginType,
                'status' => (string) ($plugin['status'] ?? ''),
                'version' => (string) ($manifest['version'] ?? ''),
            ],
            'config' => $configPayload,
        ];
    }

    /**
     * Fields/target_entity preview for a not-yet-installed plugin, built from
     * its disk schema.json/manifest.json — same row shape buildConfigResponse()
     * produces for an installed plugin, so PluginConfig's "Campos"/"Relación de
     * extensión" sections render identically whether the admin is creating a
     * new instance or editing an existing one.
     *
     * @param array<string, mixed> $manifest
     * @return array<string, mixed>
     */
    public function buildAvailablePluginPreview(array $manifest): array
    {
        try {
            $schema = $this->pluginSourceService->readSchema($manifest);
        } catch (Throwable) {
            $schema = null;
        }

        $decoded = is_array($schema) ? $schema : ['fields' => []];
        $normalizedFields = PluginSchemaFieldNormalizer::normalize($decoded['fields'] ?? null);
        $decoded['fields'] = $normalizedFields ?? [];

        if ((string) ($manifest['type'] ?? '') === 'extension') {
            $targetEntity = (string) ($manifest['target_entity'] ?? '*');
            return $this->extensionPluginConfigService->buildConfigPayload($decoded, $targetEntity);
        }

        return $this->buildEntityConfigPayload($decoded);
    }

    /**
     * @param array<string, mixed> $pluginRow
     * @return array<string, mixed>
     */
    private function decodePluginSchema(array $pluginRow): array
    {
        $isExtension = (string) ($pluginRow['manifest_json']['type'] ?? '') === 'extension';
        $decoded = json_decode((string) ($pluginRow['schema_json'] ?? ''), true);
        if (!is_array($decoded)) {
            if ($isExtension) {
                return ['fields' => []];
            }
            throw new InvalidArgumentException('Plugin schema is invalid.');
        }

        $normalizedFields = PluginSchemaFieldNormalizer::normalize($decoded['fields'] ?? null);
        if ($normalizedFields === null) {
            if ($isExtension) {
                $decoded['fields'] = [];
                return $decoded;
            }
            throw new InvalidArgumentException('Plugin schema fields are invalid.');
        }

        $decoded['fields'] = $normalizedFields;

        return $decoded;
    }

    /**
     * @param array<string, mixed> $schema
    * @return array{fields: array<int, array<string, mixed>>}
     */
    private function buildEntityConfigPayload(array $schema): array
    {
        $baseFields = $this->baseFieldsFromSchema($schema);
        $suggested = $this->suggestedCatalogFromSchema($schema);
        $activeCustom = [];
        if (isset($schema['custom_fields']) && is_array($schema['custom_fields'])) {
            foreach ($schema['custom_fields'] as $entry) {
                if (!is_array($entry)) {
                    continue;
                }

                $normalized = $this->fieldNormalizer->normalizeFieldDefinition($entry);
                $normalized['origin'] = (string) ($entry['origin'] ?? 'suggested');
                $activeCustom[] = $normalized;
            }
        }

        $activeByKey = [];
        foreach ($activeCustom as $field) {
            $activeByKey[$field['key']] = $field;
        }

        $rowsByKey = [];
        foreach ($baseFields as $field) {
            $rowsByKey[$field['key']] = [
                'active' => true,
                'key' => $field['key'],
                'type' => $field['type'],
                'label' => $field['label'],
                'required' => $field['required'],
                'summaryView' => $field['summaryView'],
                'locked' => true,
                'source' => 'base',
            ];
        }

        foreach ($suggested as $field) {
            $key = $field['key'];
            $activeField = $activeByKey[$key] ?? null;
            $rowsByKey[$key] = [
                'active' => $activeField !== null,
                'key' => $key,
                'type' => (string) ($activeField['type'] ?? $field['type']),
                'label' => (string) ($activeField['label'] ?? $field['label']),
                'required' => (bool) ($activeField['required'] ?? $field['required']),
                'summaryView' => (bool) ($activeField['summaryView'] ?? $field['summaryView']),
                'locked' => false,
                'source' => 'suggested',
            ];
        }

        foreach ($activeCustom as $field) {
            $key = $field['key'];
            if (isset($rowsByKey[$key])) {
                continue;
            }

            $rowsByKey[$key] = [
                'active' => true,
                'key' => $key,
                'type' => $field['type'],
                'label' => $field['label'],
                'required' => $field['required'],
                'summaryView' => $field['summaryView'],
                'locked' => false,
                'source' => 'additional',
            ];
        }

        return [
            'fields' => $this->fieldNormalizer->orderedRows($rowsByKey, $schema),
            'relations' => $this->relationsFromSchema($schema),
        ];
    }

    /**
     * Permissive read-side projection of schema.relations — tolerant of a
     * malformed/legacy entry (skips it) since this feeds a GET response, not
     * a write path; strict validation only happens in compileRelationsPayload()
     * when the admin actually saves a change (STORY 10.3 §8).
     *
     * @param array<string, mixed> $schema
     * @return array<int, array{key: string, type: string, target_entity: string, target_field: string, label: string, required: bool}>
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
            ];
        }

        return $relations;
    }

    /**
     * Validates and normalizes payload['relations'] rows for saveConfig()/
     * registerNew() — entity plugins only (extension plugins return early in
     * applyConfigPayload() before reaching this). type is always forced to
     * 'belongs_to' (STORY 10.3 §8 decision #8: the only shape this UI
     * exposes); target_entity must be an active entity plugin; target_field
     * must be a key declared in that entity's schema.identities block
     * (decision #9); a relation key must not collide with an already-used
     * field key, or it would silently shadow a base/custom field's value in
     * the record's content.
     *
     * @param array<int, mixed> $rows
     * @param array<string, bool> $fieldKeys keys already claimed by base/custom fields on this same plugin
     * @return array<int, array{key: string, type: string, target_entity: string, target_field: string, label: string, required: bool}>
     */
    private function compileRelationsPayload(array $rows, array $fieldKeys): array
    {
        $activeEntities = $this->pluginRepository->listActiveEntitySlugs();
        $compiled = [];
        $seenKeys = [];

        foreach ($rows as $entry) {
            if (!is_array($entry)) {
                throw new InvalidArgumentException('Each relation row must be an object.');
            }

            $key = trim((string) ($entry['key'] ?? ''));
            if ($key === '') {
                throw new InvalidArgumentException('Relation key is required.');
            }
            if (isset($seenKeys[$key]) || isset($fieldKeys[$key])) {
                throw new InvalidArgumentException("Relation key '{$key}' collides with an existing field or relation.");
            }
            $seenKeys[$key] = true;

            $targetEntity = trim((string) ($entry['target_entity'] ?? ''));
            if (!in_array($targetEntity, $activeEntities, true)) {
                throw new InvalidArgumentException("Relation '{$key}': target_entity '{$targetEntity}' is not an active entity.");
            }

            $targetField = trim((string) ($entry['target_field'] ?? ''));
            if (!in_array($targetField, $this->targetEntityIdentityKeys($targetEntity), true)) {
                throw new InvalidArgumentException("Relation '{$key}': target_field '{$targetField}' is not a declared identity of '{$targetEntity}'.");
            }

            $label = trim((string) ($entry['label'] ?? ''));

            $compiled[] = [
                'key' => $key,
                'type' => 'belongs_to',
                'target_entity' => $targetEntity,
                'target_field' => $targetField,
                'label' => $label === '' ? $key : $label,
                'required' => (bool) ($entry['required'] ?? false),
            ];
        }

        return $compiled;
    }

    /**
     * @return list<string>
     */
    private function targetEntityIdentityKeys(string $targetEntitySlug): array
    {
        if ($targetEntitySlug === '') {
            return [];
        }

        $targetPlugin = $this->pluginRepository->findBySlug($targetEntitySlug);
        if ($targetPlugin === null) {
            return [];
        }

        $decoded = json_decode((string) ($targetPlugin['schema_json'] ?? ''), true);
        $identities = is_array($decoded) && is_array($decoded['identities'] ?? null) ? $decoded['identities'] : [];

        return array_values(array_filter(array_keys($identities), 'is_string'));
    }

    /**
     * @param array<string, mixed> $schema
     * @return array<int, array{key: string, type: string, required: bool, label: string, summaryView: bool}>
     */
    private function baseFieldsFromSchema(array $schema): array
    {
        $base = [];
        foreach ($schema['fields'] as $key => $definition) {
            if (!is_string($key) || !is_array($definition)) {
                continue;
            }

            $base[] = $this->fieldNormalizer->normalizeFieldDefinition([
                'key' => $key,
                'type' => $definition['type'] ?? 'string',
                'required' => $definition['required'] ?? false,
                'label' => $definition['label'] ?? $key,
                'summaryView' => $definition['summaryView'] ?? true,
            ]);
        }

        return $base;
    }

    /**
     * @param array<string, mixed> $schema
     * @return array<int, array{key: string, type: string, required: bool, label: string}>
     */
    private function suggestedCatalogFromSchema(array $schema): array
    {
        $catalogSource = [];
        if (isset($schema['plugin_suggested_custom_fields']) && is_array($schema['plugin_suggested_custom_fields'])) {
            $catalogSource = $schema['plugin_suggested_custom_fields'];
        } elseif (isset($schema['custom_fields']) && is_array($schema['custom_fields'])) {
            $catalogSource = $schema['custom_fields'];
        }

        $catalog = [];
        foreach ($catalogSource as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $origin = (string) ($entry['origin'] ?? 'suggested');
            if ($origin === 'additional') {
                continue;
            }

            $field = $this->fieldNormalizer->normalizeFieldDefinition($entry);
            $catalog[$field['key']] = $field;
        }

        return array_values($catalog);
    }

    /**
     * @param array<int, array{active: bool, key: string, type: string, label: string, required: bool, summaryView: bool}> $rows
     * @param array<string, array{key: string, type: string, required: bool, label: string}> $baseByKey
     * @param array<string, array{key: string, type: string, required: bool, label: string}> $catalogByKey
     * @return array{
     *   seen_keys: array<string, bool>,
     *   ui_order: array<int, string>,
     *   suggested_catalog: array<int, array{key: string, type: string, label: string, required: bool, summaryView: bool, origin: string}>,
     *   active_custom_fields: array<int, array{key: string, type: string, label: string, required: bool, summaryView: bool, origin: string}>
     * }
     */
    private function compileEntityConfigRows(array $rows, array $baseByKey, array $catalogByKey): array
    {
        $seenKeys = [];
        $uiOrder = [];
        $suggestedCatalog = [];
        $activeCustomFields = [];

        foreach ($rows as $row) {
            $key = $row['key'];
            if (isset($seenKeys[$key])) {
                throw new InvalidArgumentException("Duplicated field key '{$key}'.");
            }

            $seenKeys[$key] = true;
            $uiOrder[] = $key;

            if (isset($baseByKey[$key])) {
                $base = $baseByKey[$key];
                $invalidBase = !$row['active'] || (
                    $row['type'] !== $base['type']
                    || $row['label'] !== $base['label']
                    || $row['required'] !== $base['required']
                );
                if ($invalidBase) {
                    throw new InvalidArgumentException("Base field '{$key}' cannot be edited or deactivated.");
                }
                continue;
            }

            $isSuggested = isset($catalogByKey[$key]);
            $candidate = [
                'key' => $row['key'],
                'type' => $row['type'],
                'label' => $row['label'],
                'required' => $row['required'],
                'summaryView' => $row['summaryView'],
                'origin' => $isSuggested ? 'suggested' : 'additional',
            ];

            if ($isSuggested) {
                $suggestedCatalog[] = $candidate;
            }

            if ($row['active']) {
                $activeCustomFields[] = $candidate;
            }
        }

        return [
            'seen_keys' => $seenKeys,
            'ui_order' => $uiOrder,
            'suggested_catalog' => $suggestedCatalog,
            'active_custom_fields' => $activeCustomFields,
        ];
    }
}
