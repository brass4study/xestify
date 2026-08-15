<?php

declare(strict_types=1);

namespace Xestify\plugins\schema;

use DomainException;

final class InstalledPluginSchemaValidator
{
    private const ASSOCIATIVE_SECTIONS = ['identities', 'fields'];
    private const LIST_SECTIONS = ['custom_fields', 'relations'];
    private const CUSTOM_FIELDS_SECTION = 'custom_fields';

    public function __construct(private SchemaComparisonUtil $schemaComparison = new SchemaComparisonUtil())
    {
    }

    /**
     * @param array<string, mixed>|null $installedSchema
     * @param array<string, mixed>|null $canonicalSchema
     */
    public function assertContainsCanonical(string $slug, ?array $installedSchema, ?array $canonicalSchema): void
    {
        $installed = $this->requireSchema($slug, $installedSchema, 'installed');
        $canonical = $this->requireSchema($slug, $canonicalSchema, 'canonical');

        $this->assertAssociativeSectionsContainCanonical($slug, $installed, $canonical);
        $this->assertListSectionsContainCanonical($slug, $installed, $canonical);
    }

    /**
     * @param array<string, mixed>|null $installedSchema
     * @param array<string, mixed>|null $targetSchema
     */
    public function assertCanApplyUpdate(string $slug, ?array $installedSchema, ?array $targetSchema): void
    {
        $installed = $this->requireSchema($slug, $installedSchema, 'installed');
        $target = $this->requireSchema($slug, $targetSchema, 'target');

        $this->assertInstalledSectionsExist($slug, $installed, $target);
        $this->assertOverlappingAssociativeDefinitionsMatch($slug, $installed, $target);
        $this->assertOverlappingListDefinitionsMatch($slug, $installed, $target);
    }

    /**
     * @param array<string, mixed>|null $schema
     * @return array<string, mixed>
     */
    private function requireSchema(string $slug, ?array $schema, string $kind): array
    {
        if ($schema === null || $schema === []) {
            throw new DomainException("Plugin '{$slug}' has a corrupt {$kind} schema.");
        }

        return $schema;
    }

    /**
     * @param array<string, mixed> $installed
     * @param array<string, mixed> $canonical
     */
    private function assertAssociativeSectionsContainCanonical(string $slug, array $installed, array $canonical): void
    {
        foreach (self::ASSOCIATIVE_SECTIONS as $section) {
            $installedMap = $this->requireArraySection($slug, $installed, $section);
            $canonicalMap = $this->arraySection($canonical, $section);

            foreach ($canonicalMap as $key => $definition) {
                if (!is_string($key)) {
                    continue;
                }

                if (!array_key_exists($key, $installedMap)) {
                    throw new DomainException("Plugin '{$slug}' has a corrupt installed schema: {$section}.{$key} missing.");
                }

                $this->assertDefinitionsMatch($slug, "{$section}.{$key}", $installedMap[$key], $definition);
            }
        }
    }

    /**
     * @param array<string, mixed> $installed
     * @param array<string, mixed> $canonical
     */
    private function assertListSectionsContainCanonical(string $slug, array $installed, array $canonical): void
    {
        foreach (self::LIST_SECTIONS as $section) {
            $installedSection = $section === self::CUSTOM_FIELDS_SECTION
                ? $this->installedCustomFieldsCatalog($installed)
                : $this->arraySection($installed, $section);
            $installedByKey = $this->indexListSection($installedSection);
            $canonicalByKey = $this->indexListSection($this->arraySection($canonical, $section));

            foreach ($canonicalByKey as $key => $definition) {
                if (!array_key_exists($key, $installedByKey)) {
                    throw new DomainException("Plugin '{$slug}' has a corrupt installed schema: {$section}.{$key} missing.");
                }

                $this->assertDefinitionsMatch($slug, "{$section}.{$key}", $installedByKey[$key], $definition);
            }
        }
    }

    /**
     * @param array<string, mixed> $installed
     * @param array<string, mixed> $target
     */
    private function assertInstalledSectionsExist(string $slug, array $installed, array $target): void
    {
        $this->requireArraySection($slug, $installed, 'fields');

        foreach (['identities', 'custom_fields', 'relations'] as $section) {
            if (isset($target[$section])) {
                $this->requireArraySection($slug, $installed, $section);
            }
        }
    }

    /**
     * @param array<string, mixed> $installed
     * @param array<string, mixed> $target
     */
    private function assertOverlappingAssociativeDefinitionsMatch(string $slug, array $installed, array $target): void
    {
        foreach (self::ASSOCIATIVE_SECTIONS as $section) {
            $installedMap = $this->arraySection($installed, $section);
            $targetMap = $this->arraySection($target, $section);

            foreach ($installedMap as $key => $definition) {
                if (is_string($key) && array_key_exists($key, $targetMap)) {
                    $this->assertDefinitionsMatch($slug, "{$section}.{$key}", $definition, $targetMap[$key]);
                }
            }
        }
    }

    /**
     * @param array<string, mixed> $installed
     * @param array<string, mixed> $target
     */
    private function assertOverlappingListDefinitionsMatch(string $slug, array $installed, array $target): void
    {
        foreach (self::LIST_SECTIONS as $section) {
            $installedSection = $section === self::CUSTOM_FIELDS_SECTION
                ? $this->installedCustomFieldsCatalog($installed)
                : $this->arraySection($installed, $section);
            $installedByKey = $this->indexListSection($installedSection);
            $targetByKey = $this->indexListSection($this->arraySection($target, $section));

            foreach ($installedByKey as $key => $definition) {
                if (array_key_exists($key, $targetByKey)) {
                    $this->assertDefinitionsMatch($slug, "{$section}.{$key}", $definition, $targetByKey[$key]);
                }
            }
        }
    }

    /**
     * @param array<string, mixed> $schema
     * @return array<string, mixed>
     */
    private function requireArraySection(string $slug, array $schema, string $section): array
    {
        if (!isset($schema[$section]) || !is_array($schema[$section])) {
            throw new DomainException("Plugin '{$slug}' has a corrupt installed schema: {$section} missing.");
        }

        return $schema[$section];
    }

    /**
     * @param array<string, mixed> $schema
     * @return array<string, mixed>
     */
    private function arraySection(array $schema, string $section): array
    {
        return isset($schema[$section]) && is_array($schema[$section]) ? $schema[$section] : [];
    }

    /**
     * `custom_fields` means the design catalog before a plugin is ever
     * configured via PluginAdministrationService::saveConfig(), and the
     * admin-curated list of *active* fields afterwards — at that point the
     * catalog moves to `plugin_suggested_custom_fields`. Canonical/target
     * containment checks must always compare against the catalog, never
     * against the active list, or an inactive suggested field (or one whose
     * label the admin customized after activating it) looks like corruption.
     *
     * @param array<string, mixed> $installed
     * @return array<string, mixed>
     */
    private function installedCustomFieldsCatalog(array $installed): array
    {
        if (isset($installed['plugin_suggested_custom_fields']) && is_array($installed['plugin_suggested_custom_fields'])) {
            return $installed['plugin_suggested_custom_fields'];
        }

        return $this->arraySection($installed, self::CUSTOM_FIELDS_SECTION);
    }

    /**
     * @param array<string, mixed> $section
     * @return array<string, array<string, mixed>>
     */
    private function indexListSection(array $section): array
    {
        $indexed = [];
        foreach ($section as $item) {
            if (!is_array($item) || !isset($item['key']) || !is_string($item['key'])) {
                continue;
            }

            $indexed[$item['key']] = $item;
        }

        return $indexed;
    }

    private function assertDefinitionsMatch(string $slug, string $path, mixed $installed, mixed $expected): void
    {
        if ($this->schemaComparison->normalize($installed) !== $this->schemaComparison->normalize($expected)) {
            throw new DomainException("Plugin '{$slug}' has a corrupt installed schema: {$path} changed.");
        }
    }
}
