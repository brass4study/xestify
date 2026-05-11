<?php

declare(strict_types=1);

namespace Xestify\validation\schema;

final class SchemaFieldExtractor
{
    /**
     * @param array<string, mixed> $schema
     * @return array<string, array<string, mixed>>
     */
    public function extract(array $schema): array
    {
        $fields = [];

        foreach ($this->fieldSections($schema) as $sectionName => $section) {
            $fields = array_merge($fields, $this->extractSection($section, $sectionName));
        }

        return $fields;
    }

    /**
     * @param array<string, mixed> $schema
     * @return array<string, array<mixed>>
     */
    private function fieldSections(array $schema): array
    {
        $sections = [];
        foreach (['fields', 'custom_fields', 'identities'] as $sectionName) {
            if (isset($schema[$sectionName]) && is_array($schema[$sectionName])) {
                $sections[$sectionName] = $schema[$sectionName];
            }
        }

        return $sections;
    }

    /**
     * @param array<mixed> $section
     * @return array<string, array<string, mixed>>
     */
    private function extractSection(array $section, string $sectionName): array
    {
        $fields = [];
        foreach ($section as $key => $definition) {
            if (!is_array($definition) || $this->shouldSkip($definition, $sectionName)) {
                continue;
            }

            $name = is_string($key) ? $key : $this->resolveFieldName($definition);
            if ($name === null || trim($name) === '') {
                continue;
            }

            $fields[$name] = $definition;
        }

        return $fields;
    }

    /** @param array<string, mixed> $definition */
    private function shouldSkip(array $definition, string $sectionName): bool
    {
        if ($sectionName !== 'identities') {
            return false;
        }

        return ($definition['auto_generated'] ?? false) === true
            || ($definition['editable'] ?? true) === false;
    }

    /** @param array<string, mixed> $definition */
    private function resolveFieldName(array $definition): ?string
    {
        foreach (['name', 'key', 'slug'] as $candidate) {
            if (isset($definition[$candidate]) && is_string($definition[$candidate])) {
                return trim($definition[$candidate]);
            }
        }

        return null;
    }
}
