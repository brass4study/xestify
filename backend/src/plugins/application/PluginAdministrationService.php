<?php

declare(strict_types=1);

namespace Xestify\plugins\application;

use DomainException;
use InvalidArgumentException;
use OutOfBoundsException;
use Xestify\repositories\PluginRepository;

class PluginAdministrationService
{
    public function __construct(
        private PluginRepository $pluginRepository,
        private PluginSyncService $pluginSyncService,
        private PluginOutdatedService $pluginOutdatedService,
        private PluginUpdateService $pluginUpdateService,
        private PluginStatusService $pluginStatusService,
        private ExtensionPluginConfigService $extensionPluginConfigService
    ) {
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listInstalled(): array
    {
        return $this->pluginRepository->listInstalled();
    }

    /**
     * @return array{
     *   summary: array{discovered:int, registered:int, unchanged:int, outdated:int, errors:int},
     *   plugins: array<string, array<string, mixed>>
     * }
     */
    public function syncAll(): array
    {
        return $this->pluginSyncService->syncAll();
    }

    /**
     * @return array<int, array{
     *   slug: string,
     *   name: string,
     *   plugin_type: string,
     *   installed_version: string,
     *   available_version: string
     * }>
     */
    public function getOutdated(): array
    {
        return $this->pluginOutdatedService->getOutdated();
    }

    /**
     * @return array{
     *   plugin: array<string, mixed>,
     *   update: array<string, mixed>
     * }
     */
    public function update(string $slug): array
    {
        return $this->pluginUpdateService->update($slug);
    }

    /**
     * @return array<string, mixed>
     */
    public function activate(string $slug): array
    {
        return $this->pluginStatusService->activate($slug);
    }

    /**
     * @return array<string, mixed>
     */
    public function deactivate(string $slug): array
    {
        return $this->pluginStatusService->deactivate($slug);
    }

    /**
     * @return array{plugin: array<string, mixed>, config: array<string, mixed>}
     */
    public function getConfig(string $slug): array
    {
        $plugin = $this->pluginRepository->findBySlug($slug);
        if ($plugin === null) {
            throw new OutOfBoundsException("Plugin '{$slug}' was not found.");
        }

        $this->assertConfigurablePlugin($plugin);

        return $this->buildConfigResponse($plugin);
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{plugin: array<string, mixed>, config: array<string, mixed>}
     */
    public function saveConfig(string $slug, array $payload): array
    {
        $plugin = $this->pluginRepository->findBySlug($slug);
        if ($plugin === null) {
            throw new OutOfBoundsException("Plugin '{$slug}' was not found.");
        }

        $this->assertConfigurablePlugin($plugin);

        $currentSchema = $this->decodePluginSchema($plugin);
        if (($plugin['plugin_type'] ?? '') === 'extension') {
            $updated = $this->extensionPluginConfigService->saveConfig(
                $slug,
                $currentSchema,
                $payload,
                fn(array $rows): array => $this->normalizePayloadRows($rows)
            );

            return $this->buildConfigResponse($updated);
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

        $fields = $this->normalizePayloadRows($payload['fields']);
        $compiled = $this->compileEntityConfigRows($fields, $baseByKey, $catalogByKey);

        foreach ($baseFields as $base) {
            if (!isset($compiled['seen_keys'][$base['key']])) {
                throw new InvalidArgumentException("Base field '{$base['key']}' is missing from payload.");
            }
        }

        $nextSchema = $currentSchema;
        $nextSchema['plugin_suggested_custom_fields'] = $compiled['suggested_catalog'];
        $nextSchema['custom_fields'] = $compiled['active_custom_fields'];
        $nextSchema['ui_field_order'] = $compiled['ui_order'];

        $updated = $this->pluginRepository->updateSchemaConfig($slug, $nextSchema);
        if ($updated === null) {
            throw new OutOfBoundsException("Plugin '{$slug}' was not found.");
        }

        return $this->buildConfigResponse($updated);
    }

    /**
     * @param array<int, array{active: bool, key: string, type: string, label: string, required: bool}> $rows
     * @param array<string, array{key: string, type: string, required: bool, label: string}> $baseByKey
     * @param array<string, array{key: string, type: string, required: bool, label: string}> $catalogByKey
     * @return array{
     *   seen_keys: array<string, bool>,
     *   ui_order: array<int, string>,
     *   suggested_catalog: array<int, array{key: string, type: string, label: string, required: bool, origin: string}>,
     *   active_custom_fields: array<int, array{key: string, type: string, label: string, required: bool, origin: string}>
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

    /**
     * @param array<string, mixed> $plugin
     */
    private function assertConfigurablePlugin(array $plugin): void
    {
        $pluginType = (string) ($plugin['plugin_type'] ?? '');
        if (!in_array($pluginType, ['entity', 'extension'], true)) {
            throw new DomainException('Only entity or extension plugins can be configured.');
        }

        if (($plugin['status'] ?? null) !== 'active') {
            throw new DomainException('Only active plugins can be configured.');
        }
    }

    /**
     * @param array<string, mixed> $pluginRow
     * @return array<string, mixed>
     */
    private function decodePluginSchema(array $pluginRow): array
    {
        $isExtension = (string) ($pluginRow['plugin_type'] ?? '') === 'extension';
        $decoded = json_decode((string) ($pluginRow['schema_json'] ?? ''), true);
        if (!is_array($decoded)) {
            if ($isExtension) {
                return [
                    'fields' => [],
                    'target_entity' => '*',
                ];
            }
            throw new InvalidArgumentException('Plugin schema is invalid.');
        }

        $normalizedFields = $this->normalizeSchemaFields($decoded['fields'] ?? null);
        if ($normalizedFields === null) {
            if ($isExtension) {
                $decoded['fields'] = [];
                if (!isset($decoded['target_entity']) || !is_string($decoded['target_entity'])) {
                    $decoded['target_entity'] = '*';
                }
                return $decoded;
            }
            throw new InvalidArgumentException('Plugin schema fields are invalid.');
        }

        $decoded['fields'] = $normalizedFields;

        if ($isExtension && (!isset($decoded['target_entity']) || !is_string($decoded['target_entity']))) {
            $decoded['target_entity'] = '*';
        }

        return $decoded;
    }

    /**
     * @param mixed $rawFields
     * @return array<string, array{type: string, required: bool, label: string}>|null
     */
    private function normalizeSchemaFields(mixed $rawFields): ?array
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

    /**
     * @param array<string, mixed> $plugin
     * @return array{plugin: array<string, mixed>, config: array<string, mixed>}
     */
    private function buildConfigResponse(array $plugin): array
    {
        $schema = $this->decodePluginSchema($plugin);
        $pluginType = (string) ($plugin['plugin_type'] ?? '');

        if ($pluginType === 'extension') {
            $configPayload = $this->extensionPluginConfigService->buildConfigPayload(
                $schema,
                fn(array $entry): array => $this->normalizeFieldDefinition($entry),
                fn(array $rowsByKey, array $schemaValue): array => $this->orderedRows($rowsByKey, $schemaValue)
            );
        } else {
            $configPayload = $this->buildEntityConfigPayload($schema);
        }

        return [
            'plugin' => [
                'slug' => (string) ($plugin['slug'] ?? ''),
                'name' => (string) ($plugin['name'] ?? ''),
                'plugin_type' => (string) ($plugin['plugin_type'] ?? ''),
                'status' => (string) ($plugin['status'] ?? ''),
                'version' => (string) ($plugin['version'] ?? ''),
                'schema_version' => (int) ($plugin['schema_version'] ?? 1),
            ],
            'config' => $configPayload,
        ];
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

                $normalized = $this->normalizeFieldDefinition($entry);
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
                'locked' => false,
                'source' => 'additional',
            ];
        }

        return ['fields' => $this->orderedRows($rowsByKey, $schema)];
    }

    /**
     * @param array<string, mixed> $schema
     * @return array<int, array{key: string, type: string, required: bool, label: string}>
     */
    private function baseFieldsFromSchema(array $schema): array
    {
        $base = [];
        foreach ($schema['fields'] as $key => $definition) {
            if (!is_string($key) || !is_array($definition)) {
                continue;
            }

            $base[] = $this->normalizeFieldDefinition([
                'key' => $key,
                'type' => $definition['type'] ?? 'string',
                'required' => $definition['required'] ?? false,
                'label' => $definition['label'] ?? $key,
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

            $field = $this->normalizeFieldDefinition($entry);
            $catalog[$field['key']] = $field;
        }

        return array_values($catalog);
    }

    /**
     * @param array<string, array<string, mixed>> $rowsByKey
     * @param array<string, mixed> $schema
     * @return array<int, array<string, mixed>>
     */
    private function orderedRows(array $rowsByKey, array $schema): array
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

    /**
     * @param array<int, mixed> $rows
     * @return array<int, array{active: bool, key: string, type: string, label: string, required: bool}>
     */
    private function normalizePayloadRows(array $rows): array
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
            ];
        }

        return $normalized;
    }

    /**
     * @param array<string, mixed> $entry
     * @return array{key: string, type: string, required: bool, label: string}
     */
    private function normalizeFieldDefinition(array $entry): array
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
        ];
    }
}
