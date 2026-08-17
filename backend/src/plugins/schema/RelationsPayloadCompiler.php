<?php

declare(strict_types=1);

namespace Xestify\plugins\schema;

use InvalidArgumentException;
use Xestify\repositories\PluginRepository;

/**
 * Validates and normalizes a PluginConfig "Relaciones" payload — shared by
 * PluginConfigService (entity plugins) and ExtensionPluginConfigService
 * (extension plugins, STORY 10.5: relations are editable from PluginConfig
 * "igual que en todos los plugins", not just fixed on disk). type is always
 * forced to 'belongs_to' (the only shape this UI exposes); target_entity
 * must be an active entity plugin; target_field must be a key declared in
 * that entity's schema.identities block; a relation key must not collide
 * with an already-used field key on the same plugin, or it would silently
 * shadow a base/custom field's value in the record's content.
 *
 * `layer` (STORY 10.5 "layers" convention, renamed from `group`) is a named
 * UI zone (e.g. 'od'/'os'/'general' for plugins/optometries) with no
 * meaning for entity relations — harmless there, just carried through and
 * defaulted to 'general' so both callers share one compiled shape.
 */
final class RelationsPayloadCompiler
{
    public function __construct(private PluginRepository $pluginRepository)
    {
    }

    /**
     * @param array<int, mixed> $rows
     * @param array<string, bool> $fieldKeys keys already claimed by base/custom fields on this same plugin
     * @return array<int, array{key: string, type: string, target_entity: string, target_field: string, label: string, required: bool, layer: string}>
     */
    public function compile(array $rows, array $fieldKeys): array
    {
        $activeEntities = $this->pluginRepository->listActiveEntitySlugs();
        $compiled = [];
        $seenKeys = [];

        foreach ($rows as $entry) {
            $row = $this->compileRow($entry, $activeEntities, $seenKeys, $fieldKeys);
            $seenKeys[$row['key']] = true;
            $compiled[] = $row;
        }

        return $compiled;
    }

    /**
     * Validates and normalizes one relation row. $seenKeys/$fieldKeys are
     * read-only here — the caller records each accepted key.
     *
     * @param array<string, bool> $seenKeys
     * @param array<string, bool> $fieldKeys
     * @param list<string> $activeEntities
     * @return array{key: string, type: string, target_entity: string, target_field: string, label: string, required: bool, layer: string}
     */
    private function compileRow(mixed $entry, array $activeEntities, array $seenKeys, array $fieldKeys): array
    {
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

        $targetEntity = trim((string) ($entry['target_entity'] ?? ''));
        if (!in_array($targetEntity, $activeEntities, true)) {
            throw new InvalidArgumentException("Relation '{$key}': target_entity '{$targetEntity}' is not an active entity.");
        }

        $targetField = trim((string) ($entry['target_field'] ?? ''));
        if (!in_array($targetField, $this->targetEntityIdentityKeys($targetEntity), true)) {
            throw new InvalidArgumentException("Relation '{$key}': target_field '{$targetField}' is not a declared identity of '{$targetEntity}'.");
        }

        $label = trim((string) ($entry['label'] ?? ''));
        $layer = trim((string) ($entry['layer'] ?? ''));

        return [
            'key' => $key,
            'type' => 'belongs_to',
            'target_entity' => $targetEntity,
            'target_field' => $targetField,
            'label' => $label === '' ? $key : $label,
            'required' => (bool) ($entry['required'] ?? false),
            'layer' => $layer === '' ? 'general' : $layer,
        ];
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
}
