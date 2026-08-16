<?php

declare(strict_types=1);

namespace Xestify\plugins\schema;

use Xestify\repositories\PluginRepository;

/**
 * Resolves "reverse relation" tabs for EntityEdit (STORY 10.3 §9). When
 * plugin B declares a schema.relations[] entry with target_entity = A
 * (§8), a record of A should show a tab listing B's records that point
 * back at it. This has no "owner plugin" contributing its own plugin.js
 * (unlike the Comments hook-based tab), so it is resolved here as a core
 * capability rather than another plugin hook.
 */
class ReverseRelationTabResolver
{
    public function __construct(private PluginRepository $pluginRepository)
    {
    }

    /**
     * @return array<int, array{id: string, label: string, type: string, source_entity: string, key: string}>
     */
    public function resolve(string $targetEntitySlug): array
    {
        $tabs = [];

        foreach ($this->pluginRepository->listActiveEntitySlugs() as $sourceSlug) {
            if ($sourceSlug === $targetEntitySlug) {
                continue;
            }

            $tabs = array_merge($tabs, $this->tabsFor($sourceSlug, $targetEntitySlug));
        }

        return $tabs;
    }

    /**
     * The tab label is the source entity's own label (e.g. "Items"), not
     * the relation field's label (e.g. "Comprador") — the tab lists
     * *records of that entity*, so it should read like an entity, not a
     * form field. When the same source entity declares more than one
     * relation pointing at the same target (e.g. "Comprador" and
     * "Vendedor" both belongs_to "persons"), the relation label is
     * appended to disambiguate the otherwise-identical tabs.
     *
     * @return array<int, array{id: string, label: string, type: string, source_entity: string, key: string}>
     */
    private function tabsFor(string $sourceSlug, string $targetEntitySlug): array
    {
        $source = $this->pluginRepository->findBySlug($sourceSlug);
        if ($source === null) {
            return [];
        }

        $relations = $this->relationsTargeting($source, $targetEntitySlug);
        if ($relations === []) {
            return [];
        }

        $manifest = is_array($source['manifest_json'] ?? null) ? $source['manifest_json'] : [];
        $entityLabel = isset($manifest['label']) && is_string($manifest['label']) && $manifest['label'] !== ''
            ? $manifest['label']
            : $sourceSlug;
        $ambiguous = count($relations) > 1;

        $tabs = [];
        foreach ($relations as $relation) {
            $tabs[] = [
                'id' => "relation:{$sourceSlug}:{$relation['key']}",
                'label' => $ambiguous ? "{$entityLabel} ({$relation['label']})" : $entityLabel,
                'type' => 'relation',
                'source_entity' => $sourceSlug,
                'key' => $relation['key'],
            ];
        }

        return $tabs;
    }

    /**
     * @param array<string, mixed> $source
     * @return array<int, array{key: string, label: string}>
     */
    private function relationsTargeting(array $source, string $targetEntitySlug): array
    {
        $decoded = json_decode((string) ($source['schema_json'] ?? ''), true);
        $relations = is_array($decoded) && is_array($decoded['relations'] ?? null) ? $decoded['relations'] : [];

        $matches = [];
        foreach ($relations as $relation) {
            if (!is_array($relation) || (string) ($relation['target_entity'] ?? '') !== $targetEntitySlug) {
                continue;
            }

            $key = (string) ($relation['key'] ?? '');
            if ($key === '') {
                continue;
            }

            $matches[] = [
                'key' => $key,
                'label' => (string) ($relation['label'] ?? $key),
            ];
        }

        return $matches;
    }
}
