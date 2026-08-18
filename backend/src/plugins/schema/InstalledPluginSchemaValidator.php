<?php

declare(strict_types=1);

namespace Xestify\plugins\schema;

use DomainException;

final class InstalledPluginSchemaValidator
{
    private const ASSOCIATIVE_SECTIONS = ['identities', 'fields'];
    private const LIST_SECTIONS = ['custom_fields', 'relations'];
    private const CUSTOM_FIELDS_SECTION = 'custom_fields';

    /**
     * Definition attributes PluginConfig lets the admin change (or stamps as
     * metadata) on otherwise immutable base fields: `summaryView` ("Cabecera",
     * PluginConfigService::applyBaseSummaryView()), `layer` (STORY 10.5 UI zone,
     * editable when the author left it resortable) and `origin` (config
     * bookkeeping never present in schema.json). None of them changes the
     * shape of stored data, so a routine sync must not read them as corruption.
     */
    private const DISPLAY_ONLY_KEYS = ['summaryView', 'layer', 'origin'];

    public function __construct(private SchemaComparisonUtil $schemaComparison = new SchemaComparisonUtil())
    {
    }

    /**
     * Routine (unchanged-version) sync check: the installed schema must still
     * contain everything the on-disk schema declares as immutable — every
     * canonical `identities` and `fields` definition, compared without the
     * display-only attributes above. Sections the admin owns through
     * PluginConfig are deliberately NOT compared: `custom_fields` (suggested
     * fields can be edited, deactivated or removed from the catalog) and
     * `relations` (configured per installation, STORY 10.4/10.5) — treating
     * a legitimate configuration as "corrupt" was exactly the false positive
     * this rule closes. A section the canonical schema does not declare at
     * all (extension plugins have no `identities`) is not required either.
     *
     * @param array<string, mixed>|null $installedSchema
     * @param array<string, mixed>|null $canonicalSchema
     */
    public function assertContainsCanonical(string $slug, ?array $installedSchema, ?array $canonicalSchema): void
    {
        $installed = $this->requireSchema($slug, $installedSchema, 'installed');
        $canonical = $this->requireSchema($slug, $canonicalSchema, 'canonical');

        $this->assertAssociativeSectionsContainCanonical($slug, $installed, $canonical);
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
            if (!isset($canonical[$section]) || !is_array($canonical[$section])) {
                // The disk schema does not declare this section (extension
                // plugins carry no `identities`), so nothing to contain.
                continue;
            }

            $installedMap = $this->requireArraySection($slug, $installed, $section);

            foreach ($canonical[$section] as $key => $definition) {
                if (!is_string($key)) {
                    continue;
                }

                if (!array_key_exists($key, $installedMap)) {
                    throw new DomainException("Plugin '{$slug}' has a corrupt installed schema: {$section}.{$key} missing.");
                }

                $this->assertDefinitionsMatch(
                    $slug,
                    "{$section}.{$key}",
                    $installedMap[$key],
                    $definition,
                    self::DISPLAY_ONLY_KEYS
                );
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

    /**
     * @param list<string> $ignoreKeys top-level definition attributes excluded from the comparison
     */
    private function assertDefinitionsMatch(
        string $slug,
        string $path,
        mixed $installed,
        mixed $expected,
        array $ignoreKeys = []
    ): void {
        $left = $this->schemaComparison->normalize($this->withoutKeys($installed, $ignoreKeys));
        $right = $this->schemaComparison->normalize($this->withoutKeys($expected, $ignoreKeys));

        if ($left !== $right) {
            throw new DomainException("Plugin '{$slug}' has a corrupt installed schema: {$path} changed.");
        }
    }

    /**
     * @param list<string> $ignoreKeys
     */
    private function withoutKeys(mixed $value, array $ignoreKeys): mixed
    {
        if ($ignoreKeys === [] || !is_array($value)) {
            return $value;
        }

        return array_diff_key($value, array_flip($ignoreKeys));
    }
}
