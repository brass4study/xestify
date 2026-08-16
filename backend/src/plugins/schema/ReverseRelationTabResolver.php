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

            foreach ($this->relationsTargeting($sourceSlug, $targetEntitySlug) as $relation) {
                $tabs[] = [
                    'id' => "relation:{$sourceSlug}:{$relation['key']}",
                    'label' => $relation['label'],
                    'type' => 'relation',
                    'source_entity' => $sourceSlug,
                    'key' => $relation['key'],
                ];
            }
        }

        return $tabs;
    }

    /**
     * @return array<int, array{key: string, label: string}>
     */
    private function relationsTargeting(string $sourceSlug, string $targetEntitySlug): array
    {
        $source = $this->pluginRepository->findBySlug($sourceSlug);
        if ($source === null) {
            return [];
        }

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
