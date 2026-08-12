<?php

declare(strict_types=1);

namespace Xestify\plugins\application;

use DomainException;
use Xestify\exceptions\PluginException;

final class PluginSchemaMergeService
{
    private const SECTIONS = ['identities', 'fields', 'custom_fields', 'relations'];

    public function __construct(private SchemaComparisonUtil $schemaComparison = new SchemaComparisonUtil())
    {
    }

    /**
     * @param array<string, mixed>|null $currentSchema
     * @param array<string, mixed>|null $targetSchema
     * @return array{
     *   schema: array<string, mixed>,
     *   changed: bool,
     *   diff: array<string, array{added: string[]}>
     * }
     */
    public function mergeAdditively(string $slug, ?array $currentSchema, ?array $targetSchema): array
    {
        if ($targetSchema === null) {
            throw new PluginException("schema.json not found for entity plugin: {$slug}");
        }

        $merged = $currentSchema ?? [];
        $diff = $this->emptyDiff();

        if (isset($merged['entity'], $targetSchema['entity']) && $merged['entity'] !== $targetSchema['entity']) {
            throw new DomainException("Plugin '{$slug}' update is not additive: entity identifier changed.");
        }

        if (!isset($merged['entity']) && isset($targetSchema['entity'])) {
            $merged['entity'] = $targetSchema['entity'];
        }

        if (isset($targetSchema['version']) && is_string($targetSchema['version'])) {
            $merged['version'] = $targetSchema['version'];
        }

        if (!isset($merged['label_singular']) && isset($targetSchema['label_singular'])) {
            $merged['label_singular'] = $targetSchema['label_singular'];
        }

        $merged['identities'] = $this->mergeAssociativeSection(
            $slug,
            'identities',
            $merged['identities'] ?? [],
            $targetSchema['identities'] ?? [],
            $diff
        );

        $merged['fields'] = $this->mergeAssociativeSection(
            $slug,
            'fields',
            $merged['fields'] ?? [],
            $targetSchema['fields'] ?? [],
            $diff
        );

        $merged = $this->mergeCustomFieldsSection($slug, $merged, $targetSchema, $diff);

        $merged['relations'] = $this->mergeListSectionByKey(
            $slug,
            'relations',
            $merged['relations'] ?? [],
            $targetSchema['relations'] ?? [],
            $diff
        );

        return [
            'schema' => $merged,
            'changed' => $this->hasChanges($diff),
            'diff' => $diff,
        ];
    }

    /**
     * @return array<string, array{added: string[]}>
     */
    public function emptyDiff(): array
    {
        $diff = [];
        foreach (self::SECTIONS as $section) {
            $diff[$section] = ['added' => []];
        }

        return $diff;
    }

    /**
     * @param mixed $current
     * @param mixed $target
     * @param array<string, array{added: string[]}> $diff
     * @return array<string, mixed>
     */
    private function mergeAssociativeSection(
        string $slug,
        string $section,
        mixed $current,
        mixed $target,
        array &$diff
    ): array {
        $currentMap = is_array($current) ? $current : [];
        $targetMap = is_array($target) ? $target : [];

        foreach ($targetMap as $key => $definition) {
            if (!is_string($key)) {
                continue;
            }

            if (array_key_exists($key, $currentMap)) {
                if (!$this->definitionsEqual($currentMap[$key], $definition)) {
                    throw new DomainException(
                        "Plugin '{$slug}' update is not additive: '{$section}.{$key}' changed."
                    );
                }
                continue;
            }

            $currentMap[$key] = $definition;
            $diff[$section]['added'][] = $key;
        }

        return $currentMap;
    }

    /**
     * `custom_fields` means two different things depending on whether the plugin
     * has ever been configured via PluginAdministrationService::saveConfig():
     * before that, it IS the design catalog (mirrors schema.json); after it, the
     * catalog moves to `plugin_suggested_custom_fields` and `custom_fields`
     * becomes the admin-curated list of *active* fields consumed by EntityService
     * for record validation. Merging the on-disk catalog additively must always
     * target the catalog, never the active list — otherwise editing an already
     * active suggested field falsely looks like a breaking change, and a brand
     * new suggested field would silently become active without admin consent.
     *
     * @param array<string, mixed> $merged
     * @param array<string, mixed> $targetSchema
     * @param array<string, array{added: string[]}> $diff
     * @return array<string, mixed>
     */
    private function mergeCustomFieldsSection(string $slug, array $merged, array $targetSchema, array &$diff): array
    {
        $targetCatalog = $targetSchema['custom_fields'] ?? [];

        if (array_key_exists('plugin_suggested_custom_fields', $merged)) {
            $merged['plugin_suggested_custom_fields'] = $this->mergeListSectionByKey(
                $slug,
                'custom_fields',
                $merged['plugin_suggested_custom_fields'] ?? [],
                $targetCatalog,
                $diff
            );

            return $merged;
        }

        $merged['custom_fields'] = $this->mergeListSectionByKey(
            $slug,
            'custom_fields',
            $merged['custom_fields'] ?? [],
            $targetCatalog,
            $diff
        );

        return $merged;
    }

    /**
     * @param mixed $current
     * @param mixed $target
     * @param array<string, array{added: string[]}> $diff
     * @return array<int, array<string, mixed>>
     */
    private function mergeListSectionByKey(
        string $slug,
        string $section,
        mixed $current,
        mixed $target,
        array &$diff
    ): array {
        $currentList = $this->normalizeItemList($current);
        $targetList = $this->normalizeItemList($target);
        $currentByKey = $this->indexByKey($currentList);

        foreach ($targetList as $item) {
            $key = $this->itemKey($item);
            if ($key === null) {
                continue;
            }

            if (isset($currentByKey[$key])) {
                if (!$this->definitionsEqual($currentByKey[$key], $item)) {
                    throw new DomainException(
                        "Plugin '{$slug}' update is not additive: '{$section}.{$key}' changed."
                    );
                }

                continue;
            }

            $currentList[] = $item;
            $currentByKey[$key] = $item;
            $diff[$section]['added'][] = $key;
        }

        return $currentList;
    }

    /**
     * @param mixed $items
     * @return array<int, array<string, mixed>>
     */
    private function normalizeItemList(mixed $items): array
    {
        return is_array($items) ? array_values(array_filter($items, 'is_array')) : [];
    }

    /**
     * @param array<int, array<string, mixed>> $items
     * @return array<string, array<string, mixed>>
     */
    private function indexByKey(array $items): array
    {
        $indexed = [];
        foreach ($items as $item) {
            $key = $this->itemKey($item);
            if ($key !== null) {
                $indexed[$key] = $item;
            }
        }

        return $indexed;
    }

    /**
     * @param array<string, mixed> $item
     */
    private function itemKey(array $item): ?string
    {
        return isset($item['key']) && is_string($item['key']) ? $item['key'] : null;
    }

    private function definitionsEqual(mixed $left, mixed $right): bool
    {
        return $this->schemaComparison->normalize($left) === $this->schemaComparison->normalize($right);
    }

    /**
     * @param array<string, array{added: string[]}> $diff
     */
    private function hasChanges(array $diff): bool
    {
        foreach ($diff as $sectionDiff) {
            if ($sectionDiff['added'] !== []) {
                return true;
            }
        }

        return false;
    }
}
