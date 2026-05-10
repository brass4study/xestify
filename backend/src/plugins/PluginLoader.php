<?php

declare(strict_types=1);

namespace Xestify\plugins;

use DomainException;
use OutOfBoundsException;
use PDO;
use Throwable;
use Xestify\exceptions\PluginException;

/**
 * PluginLoader — discovers, validates and registers backend plugins.
 *
 * Each plugin lives in a subdirectory of $pluginsDir:
 *   {slug}/manifest.json  Required fields: slug, name, version, type, core_version
 *   {slug}/Hooks.php      Optional — loaded via require_once when present
 *   {slug}/Lifecycle.php  Optional — loaded via require_once when present
 *   {slug}/plugin.js      Optional — frontend entry point, served to the browser
 *
 * Compatibility rule: plugin's core_version must be <= current CORE_VERSION.
 *
 * Lifecycle:
 *   - onInstall()    called the first time a plugin is registered
 *   - onActivate()   called via activate($slug)
 *   - onDeactivate() called via deactivate($slug)
 *   - onUpdate()     optional convention: called during explicit update if present
 */
class PluginLoader
{
    public const CORE_VERSION = '1.0.0';

    private const PLUGIN_NAMESPACE_PREFIX = 'Xestify\\plugins\\';
    private const MANIFEST_REQUIRED_FIELDS = ['slug', 'name', 'version', 'type', 'core_version'];
    private const VALID_TYPES = ['entity', 'extension'];
    private const SYNC_RESULT_REGISTERED = 'registered';
    private const SYNC_RESULT_UNCHANGED = 'unchanged';
    private const SYNC_RESULT_OUTDATED = 'outdated';
    private const SYNC_RESULT_ERROR = 'error';
    private const SCHEMA_SECTIONS = ['identities', 'fields', 'custom_fields', 'relations'];

    private string $pluginsDir;
    private PDO $pdo;

    public function __construct(string $pluginsDir, PDO $pdo)
    {
        $this->pluginsDir = rtrim($pluginsDir, '/\\');
        $this->pdo = $pdo;
    }

    /**
     * Scan the plugins directory and return all slugs that have a manifest.json.
     *
     * @return string[]
     */
    public function discover(): array
    {
        if (!is_dir($this->pluginsDir)) {
            return [];
        }

        $slugs = [];
        $entries = scandir($this->pluginsDir) ?: [];

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $pluginDir = $this->pluginsDir . '/' . $entry;
            if (!is_dir($pluginDir)) {
                continue;
            }

            if (file_exists($pluginDir . '/manifest.json')) {
                $slugs[] = $entry;
            }
        }

        return $slugs;
    }

    /**
     * Load a single plugin using the same semantics as explicit sync:
     * new plugins are registered, existing plugins preserve their runtime version/schema.
     *
     * @throws PluginException
     */
    public function load(string $slug): array
    {
        $manifest = $this->readManifest($slug);
        $this->syncManifest($manifest);

        return $manifest;
    }

    /**
     * Activate a plugin: update status to 'active' and call onActivate().
     */
    public function activate(string $slug): void
    {
        $this->updateStatus($slug, 'active');
        $this->requireLifecycleFile($slug);
        $lifecycle = $this->instantiateLifecycle($slug);
        if ($lifecycle !== null) {
            $lifecycle->onActivate();
        }
    }

    /**
     * Deactivate a plugin: update status to 'inactive' and call onDeactivate().
     */
    public function deactivate(string $slug): void
    {
        $this->updateStatus($slug, 'inactive');
        $this->requireLifecycleFile($slug);
        $lifecycle = $this->instantiateLifecycle($slug);
        if ($lifecycle !== null) {
            $lifecycle->onDeactivate();
        }
    }

    /**
     * Load all discovered plugins.
     *
     * @return array<string, array>
     * @throws PluginException
     */
    public function loadAll(): array
    {
        $loaded = [];

        foreach ($this->discover() as $slug) {
            $loaded[$slug] = $this->load($slug);
        }

        return $loaded;
    }

    /**
     * Best-effort sync of disk plugins into the registry.
     *
     * New plugins are registered. Existing plugins preserve version/schema/runtime state.
     * When disk version is greater, the plugin is reported as outdated but not updated.
     *
     * @return array{
     *   summary: array{discovered:int, registered:int, unchanged:int, outdated:int, errors:int},
     *   plugins: array<string, array<string, mixed>>
     * }
     */
    public function syncAll(): array
    {
        $summary = [
            'discovered' => 0,
            'registered' => 0,
            'unchanged' => 0,
            'outdated' => 0,
            'errors' => 0,
        ];
        $results = [];

        $slugs = $this->discover();
        $summary['discovered'] = count($slugs);

        foreach ($slugs as $slug) {
            try {
                $manifest = $this->readManifest($slug);
                $result = $this->syncManifest($manifest);
                $results[$slug] = $result;
                $summary[$result['result']]++;
            } catch (Throwable $e) {
                $results[$slug] = [
                    'slug' => $slug,
                    'result' => self::SYNC_RESULT_ERROR,
                    'message' => $e->getMessage(),
                ];
                $summary['errors']++;
            }
        }

        return ['summary' => $summary, 'plugins' => $results];
    }

    /**
     * Explicit plugin update from disk to the installed runtime state.
     *
     * @return array{
     *   plugin: array<string, mixed>,
     *   update: array{
     *     from_version: string,
     *     to_version: string,
     *     schema_changed: bool,
     *     diff: array<string, array{added: string[]}>
     *   }
     * }
     *
     * @throws OutOfBoundsException if the plugin is not installed
     * @throws DomainException when update conditions are not met
     * @throws PluginException on plugin source validation errors
     */
    public function update(string $slug): array
    {
        $this->pdo->beginTransaction();

        try {
            $current = $this->lockInstalledPlugin($slug);
            if ($current === null) {
                throw new OutOfBoundsException("Plugin '{$slug}' is not installed.");
            }

            $manifest = $this->readManifest($slug);
            $this->validateCompatibility($manifest);
            $this->validateDependencies($manifest);
            $this->ensureInstalledTypeMatchesManifest($current, $manifest);

            $fromVersion = (string) $current['version'];
            $toVersion = (string) $manifest['version'];

            if (version_compare($toVersion, $fromVersion, '<=')) {
                throw new DomainException(
                    "Plugin '{$slug}' has no newer version available on disk."
                );
            }

            $currentSchema = $this->decodeSchemaValue($current['schema_json'] ?? null);
            $targetSchema = $this->decodeSchemaValue($this->readEntitySchema($manifest));

            $mergedSchema = $currentSchema;
            $schemaChanged = false;
            $diff = $this->emptySchemaDiff();

            if (($manifest['type'] ?? '') === 'entity') {
                $merge = $this->mergeEntitySchemaAdditively($slug, $currentSchema, $targetSchema);
                $mergedSchema = $merge['schema'];
                $schemaChanged = $merge['changed'];
                $diff = $merge['diff'];
            }

            $this->insertUpdateSnapshot($current, $toVersion);

            $this->requireLifecycleFile($slug);
            $lifecycle = $this->instantiateLifecycle($slug);
            $context = [
                'slug' => $slug,
                'from_version' => $fromVersion,
                'to_version' => $toVersion,
                'current_plugin' => $current,
                'manifest' => $manifest,
                'current_schema' => $currentSchema,
                'target_schema' => $targetSchema,
                'merged_schema' => $mergedSchema,
            ];

            if ($lifecycle !== null && method_exists($lifecycle, 'onUpdate')) {
                $lifecycle->onUpdate($context); // @phpstan-ignore-line optional convention
            }

            $plugin = $this->persistPluginUpdate($current, $manifest, $mergedSchema, $schemaChanged);

            if ((string) ($current['status'] ?? '') === 'active' && $lifecycle !== null) {
                $lifecycle->onActivate();
            }

            $this->pdo->commit();

            return [
                'plugin' => $plugin,
                'update' => [
                    'from_version' => $fromVersion,
                    'to_version' => $toVersion,
                    'schema_changed' => $schemaChanged,
                    'diff' => $diff,
                ],
            ];
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $e;
        }
    }

    /**
     * Require and register the Hooks class of every active plugin into the dispatcher.
     * Call this once during application boot, after the container is fully wired.
     */
    public function registerActiveHooks(HookDispatcher $dispatcher): void
    {
        $stmt = $this->pdo->query("SELECT slug FROM plugins WHERE status = 'active'");
        if ($stmt === false) {
            return;
        }

        /** @var array<array{slug: string}> $rows */
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as $row) {
            $slug = (string) $row['slug'];
            $this->loadHooks($slug);
            $hooks = $this->instantiateHooks($slug);
            if ($hooks !== null) {
                $hooks->register($dispatcher);
            }
        }
    }

    /**
     * Return installed plugins whose manifest version is greater than the installed version.
     *
     * @return array<int, array{
     *     slug: string,
     *     name: string,
     *     plugin_type: string,
     *     installed_version: string,
     *     available_version: string
     * }>
     */
    public function getOutdated(): array
    {
        $stmt = $this->pdo->query('SELECT slug, name, plugin_type, version FROM plugins');
        $dbPlugins = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
        $dbBySlug = [];
        foreach ($dbPlugins as $row) {
            $dbBySlug[(string) $row['slug']] = $row;
        }

        $outdated = [];
        foreach ($this->discover() as $slug) {
            $manifest = $this->readManifest($slug);
            if (!isset($dbBySlug[$slug])) {
                continue;
            }

            $diskVersion = (string) ($manifest['version'] ?? '');
            $dbVersion = (string) ($dbBySlug[$slug]['version'] ?? '');
            if ($diskVersion === '' || $dbVersion === '') {
                continue;
            }

            if (version_compare($diskVersion, $dbVersion, '>')) {
                $outdated[] = [
                    'slug' => $slug,
                    'name' => (string) ($manifest['name'] ?? $slug),
                    'plugin_type' => (string) ($manifest['type'] ?? ''),
                    'installed_version' => $dbVersion,
                    'available_version' => $diskVersion,
                ];
            }
        }

        return $outdated;
    }

    /**
     * @throws PluginException
     */
    private function readManifest(string $slug): array
    {
        $path = $this->pluginsDir . '/' . $slug . '/manifest.json';

        if (!file_exists($path)) {
            throw new PluginException("manifest.json not found for plugin: {$slug}");
        }

        $json = file_get_contents($path);
        if ($json === false) {
            throw new PluginException("Cannot read manifest.json for plugin: {$slug}");
        }

        $data = json_decode($json, true);
        if (!is_array($data)) {
            throw new PluginException("Invalid JSON in manifest.json for plugin: {$slug}");
        }

        $this->validateManifestStructure($data, $slug);

        return $data;
    }

    /**
     * @throws PluginException
     */
    private function validateManifestStructure(array $manifest, string $slug): void
    {
        foreach (self::MANIFEST_REQUIRED_FIELDS as $field) {
            if (!isset($manifest[$field]) || !is_string($manifest[$field]) || $manifest[$field] === '') {
                throw new PluginException(
                    "manifest.json for plugin '{$slug}' is missing required field: {$field}"
                );
            }
        }

        if (!in_array($manifest['type'], self::VALID_TYPES, true)) {
            throw new PluginException(
                "Plugin '{$slug}' has invalid type '{$manifest['type']}'. Must be one of: "
                . implode(', ', self::VALID_TYPES)
            );
        }
    }

    /**
     * @throws PluginException
     */
    private function validateCompatibility(array $manifest): void
    {
        if (version_compare((string) $manifest['core_version'], self::CORE_VERSION, '>')) {
            throw new PluginException(
                "Plugin '{$manifest['slug']}' requires core >= {$manifest['core_version']}, "
                . 'current core is ' . self::CORE_VERSION
            );
        }
    }

    /**
     * @throws PluginException
     */
    private function validateDependencies(array $manifest): void
    {
        $requires = $manifest['requires'] ?? [];

        if (!is_array($requires) || $requires === []) {
            return;
        }

        foreach ($requires as $dep) {
            if (!is_array($dep) || !isset($dep['slug']) || !is_string($dep['slug'])) {
                throw new PluginException(
                    "Plugin '{$manifest['slug']}' has an invalid 'requires' entry in manifest.json"
                );
            }

            $depSlug = $dep['slug'];
            $minVersion = isset($dep['version']) && is_string($dep['version']) ? $dep['version'] : '0.0.0';

            $stmt = $this->pdo->prepare('SELECT version FROM plugins WHERE slug = :slug');
            $stmt->execute([':slug' => $depSlug]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($row === false) {
                throw new PluginException(
                    "Plugin '{$manifest['slug']}' requires plugin '{$depSlug}' which is not installed"
                );
            }

            if (version_compare((string) $row['version'], $minVersion, '<')) {
                throw new PluginException(
                    "Plugin '{$manifest['slug']}' requires plugin '{$depSlug}' >= {$minVersion}, "
                    . 'installed version is ' . $row['version']
                );
            }
        }
    }

    /**
     * Sync a manifest into the installed runtime state.
     *
     * @return array<string, mixed>
     *
     * @throws PluginException
     * @throws DomainException
     */
    private function syncManifest(array $manifest): array
    {
        $this->validateCompatibility($manifest);
        $this->validateDependencies($manifest);
        if (($manifest['type'] ?? '') === 'entity') {
            $this->readEntitySchema($manifest);
        }

        $slug = (string) $manifest['slug'];
        $existing = $this->fetchInstalledPlugin($slug);

        if ($existing === null) {
            $this->registerNewPlugin($manifest);
            $this->loadHooks($slug);
            $this->requireLifecycleFile($slug);
            $lifecycle = $this->instantiateLifecycle($slug);
            if ($lifecycle !== null) {
                $lifecycle->onInstall();
            }

            return [
                'slug' => $slug,
                'name' => (string) $manifest['name'],
                'plugin_type' => (string) $manifest['type'],
                'result' => self::SYNC_RESULT_REGISTERED,
                'installed_version' => (string) $manifest['version'],
                'available_version' => (string) $manifest['version'],
                'message' => "Plugin '{$slug}' registered.",
            ];
        }

        $this->ensureInstalledTypeMatchesManifest($existing, $manifest);
        $this->refreshInstalledMetadata($existing, $manifest);

        $installedVersion = (string) $existing['version'];
        $availableVersion = (string) $manifest['version'];
        $result = version_compare($availableVersion, $installedVersion, '>')
            ? self::SYNC_RESULT_OUTDATED
            : self::SYNC_RESULT_UNCHANGED;

        return [
            'slug' => $slug,
            'name' => (string) $manifest['name'],
            'plugin_type' => (string) $manifest['type'],
            'result' => $result,
            'installed_version' => $installedVersion,
            'available_version' => $availableVersion,
            'message' => $result === self::SYNC_RESULT_OUTDATED
                ? "Plugin '{$slug}' has an update available."
                : "Plugin '{$slug}' is already synchronized.",
        ];
    }

    /**
     * Register a brand-new plugin.
     *
     * @throws PluginException
     */
    private function registerNewPlugin(array $manifest): void
    {
        $schema = $this->readEntitySchema($manifest);

        $this->pdo->prepare(
            'INSERT INTO plugins (slug, name, plugin_type, version, status, schema_json, schema_version)
             VALUES (:slug, :name, :type, :version, :status, :schema::jsonb, 1)'
        )->execute([
            ':slug' => $manifest['slug'],
            ':name' => $manifest['name'],
            ':type' => $manifest['type'],
            ':version' => $manifest['version'],
            ':status' => 'inactive',
            ':schema' => $schema,
        ]);
    }

    private function refreshInstalledMetadata(array $existing, array $manifest): void
    {
        $name = (string) $manifest['name'];
        if ((string) $existing['name'] === $name) {
            return;
        }

        $this->pdo->prepare(
            'UPDATE plugins
                SET name = :name,
                    updated_at = NOW()
              WHERE slug = :slug'
        )->execute([
            ':name' => $name,
            ':slug' => $manifest['slug'],
        ]);
    }

    /**
     * Entity plugins must carry their runtime schema beside the manifest.
     * Extension plugins may omit schema.json.
     *
     * @return string|null JSON encoded schema for DB insertion.
     * @throws PluginException
     */
    private function readEntitySchema(array $manifest): ?string
    {
        if (($manifest['type'] ?? '') !== 'entity') {
            return null;
        }

        $slug = (string) $manifest['slug'];
        $path = $this->pluginsDir . '/' . $slug . '/schema.json';

        if (!file_exists($path)) {
            throw new PluginException("schema.json not found for entity plugin: {$slug}");
        }

        $raw = file_get_contents($path);
        if ($raw === false) {
            throw new PluginException("Cannot read schema.json for entity plugin: {$slug}");
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded) || !isset($decoded['fields']) || !is_array($decoded['fields'])) {
            throw new PluginException("Invalid schema.json for entity plugin '{$slug}': missing fields");
        }

        $schema = json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($schema === false) {
            throw new PluginException("Cannot encode schema.json for entity plugin: {$slug}");
        }

        return $schema;
    }

    private function decodeSchemaValue(mixed $schema): ?array
    {
        if ($schema === null || $schema === '') {
            return null;
        }

        if (is_array($schema)) {
            return $schema;
        }

        $decoded = is_string($schema) ? json_decode($schema, true) : null;

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function fetchInstalledPlugin(string $slug): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, slug, name, plugin_type, version, status, schema_version, schema_json, installed_at, updated_at
             FROM plugins
             WHERE slug = :slug
             LIMIT 1'
        );
        $stmt->execute([':slug' => $slug]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $row;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function lockInstalledPlugin(string $slug): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, slug, name, plugin_type, version, status, schema_version, schema_json, installed_at, updated_at
             FROM plugins
             WHERE slug = :slug
             LIMIT 1
             FOR UPDATE'
        );
        $stmt->execute([':slug' => $slug]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $row;
    }

    /**
     * @param array<string, mixed> $existing
     * @param array<string, mixed> $manifest
     */
    private function ensureInstalledTypeMatchesManifest(array $existing, array $manifest): void
    {
        if ((string) $existing['plugin_type'] !== (string) $manifest['type']) {
            throw new DomainException(
                "Plugin '{$manifest['slug']}' cannot change type from '{$existing['plugin_type']}'"
                . " to '{$manifest['type']}'."
            );
        }
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
    private function mergeEntitySchemaAdditively(
        string $slug,
        ?array $currentSchema,
        ?array $targetSchema
    ): array {
        if ($targetSchema === null) {
            throw new PluginException("schema.json not found for entity plugin: {$slug}");
        }

        $merged = $currentSchema ?? [];
        $diff = $this->emptySchemaDiff();

        if (isset($merged['entity'], $targetSchema['entity']) && $merged['entity'] !== $targetSchema['entity']) {
            throw new DomainException(
                "Plugin '{$slug}' update is not additive: entity identifier changed."
            );
        }

        if (!isset($merged['entity']) && isset($targetSchema['entity'])) {
            $merged['entity'] = $targetSchema['entity'];
        }

        if (isset($targetSchema['version']) && is_string($targetSchema['version'])) {
            $merged['version'] = $targetSchema['version'];
        }

        foreach (['label_singular'] as $metadataKey) {
            if (!isset($merged[$metadataKey]) && isset($targetSchema[$metadataKey])) {
                $merged[$metadataKey] = $targetSchema[$metadataKey];
            }
        }

        $merged['identities'] = $this->mergeAssociativeSchemaSection(
            $slug,
            'identities',
            $merged['identities'] ?? [],
            $targetSchema['identities'] ?? [],
            $diff
        );

        $merged['fields'] = $this->mergeAssociativeSchemaSection(
            $slug,
            'fields',
            $merged['fields'] ?? [],
            $targetSchema['fields'] ?? [],
            $diff
        );

        $merged['custom_fields'] = $this->mergeListSchemaSectionByKey(
            $slug,
            'custom_fields',
            $merged['custom_fields'] ?? [],
            $targetSchema['custom_fields'] ?? [],
            $diff
        );

        $merged['relations'] = $this->mergeListSchemaSectionByKey(
            $slug,
            'relations',
            $merged['relations'] ?? [],
            $targetSchema['relations'] ?? [],
            $diff
        );

        $changed = false;
        foreach ($diff as $sectionDiff) {
            if ($sectionDiff['added'] !== []) {
                $changed = true;
                break;
            }
        }

        return [
            'schema' => $merged,
            'changed' => $changed,
            'diff' => $diff,
        ];
    }

    /**
     * @param mixed $current
     * @param mixed $target
     * @param array<string, array{added: string[]}> $diff
     * @return array<string, mixed>
     */
    private function mergeAssociativeSchemaSection(
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
     * @param mixed $current
     * @param mixed $target
     * @param array<string, array{added: string[]}> $diff
     * @return array<int, array<string, mixed>>
     */
    private function mergeListSchemaSectionByKey(
        string $slug,
        string $section,
        mixed $current,
        mixed $target,
        array &$diff
    ): array {
        $currentList = $this->normalizeSchemaItemList($current);
        $targetList = $this->normalizeSchemaItemList($target);
        $currentByKey = $this->indexSchemaItemsByKey($currentList);

        foreach ($targetList as $item) {
            $key = $this->schemaItemKey($item);
            if ($key === null) {
                continue;
            }

            $this->mergeSchemaItemByKey($slug, $section, $key, $item, $currentList, $currentByKey, $diff);
        }

        return $currentList;
    }

    /**
     * @param mixed $items
     * @return array<int, array<string, mixed>>
     */
    private function normalizeSchemaItemList(mixed $items): array
    {
        return is_array($items) ? array_values(array_filter($items, 'is_array')) : [];
    }

    /**
     * @param array<int, array<string, mixed>> $items
     * @return array<string, array<string, mixed>>
     */
    private function indexSchemaItemsByKey(array $items): array
    {
        $indexed = [];
        foreach ($items as $item) {
            $key = $this->schemaItemKey($item);
            if ($key !== null) {
                $indexed[$key] = $item;
            }
        }

        return $indexed;
    }

    /**
     * @param array<string, mixed> $item
     */
    private function schemaItemKey(array $item): ?string
    {
        return isset($item['key']) && is_string($item['key']) ? $item['key'] : null;
    }

    /**
     * @param array<string, mixed> $item
     * @param array<int, array<string, mixed>> $currentList
     * @param array<string, array<string, mixed>> $currentByKey
     * @param array<string, array{added: string[]}> $diff
     */
    private function mergeSchemaItemByKey(
        string $slug,
        string $section,
        string $key,
        array $item,
        array &$currentList,
        array &$currentByKey,
        array &$diff
    ): void {
        if (isset($currentByKey[$key])) {
            if (!$this->definitionsEqual($currentByKey[$key], $item)) {
                throw new DomainException(
                    "Plugin '{$slug}' update is not additive: '{$section}.{$key}' changed."
                );
            }

            return;
        }

        $currentList[] = $item;
        $currentByKey[$key] = $item;
        $diff[$section]['added'][] = $key;
    }

    private function definitionsEqual(mixed $left, mixed $right): bool
    {
        return $this->normalizeForComparison($left) === $this->normalizeForComparison($right);
    }

    private function normalizeForComparison(mixed $value): mixed
    {
        if (!is_array($value)) {
            return $value;
        }

        if (array_is_list($value)) {
            return array_map(fn(mixed $item): mixed => $this->normalizeForComparison($item), $value);
        }

        ksort($value);
        foreach ($value as $key => $item) {
            $value[$key] = $this->normalizeForComparison($item);
        }

        return $value;
    }

    /**
     * @return array<string, array{added: string[]}>
     */
    private function emptySchemaDiff(): array
    {
        $diff = [];
        foreach (self::SCHEMA_SECTIONS as $section) {
            $diff[$section] = ['added' => []];
        }

        return $diff;
    }

    /**
     * @param array<string, mixed> $current
     */
    private function insertUpdateSnapshot(array $current, string $targetVersion): void
    {
        $this->pdo->prepare(
            'INSERT INTO plugin_update_history (
                slug, name, plugin_type, version, status, schema_version, schema_json, target_version
             ) VALUES (
                :slug, :name, :plugin_type, :version, :status, :schema_version, :schema_json::jsonb, :target_version
             )'
        )->execute([
            ':slug' => $current['slug'],
            ':name' => $current['name'],
            ':plugin_type' => $current['plugin_type'],
            ':version' => $current['version'],
            ':status' => $current['status'],
            ':schema_version' => $current['schema_version'],
            ':schema_json' => $current['schema_json'],
            ':target_version' => $targetVersion,
        ]);
    }

    /**
     * @param array<string, mixed> $current
     * @param array<string, mixed> $manifest
     * @param array<string, mixed>|null $mergedSchema
     * @return array<string, mixed>
     */
    private function persistPluginUpdate(
        array $current,
        array $manifest,
        ?array $mergedSchema,
        bool $schemaChanged
    ): array {
        if ($schemaChanged) {
            $schemaJson = json_encode($mergedSchema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if ($schemaJson === false) {
                throw new PluginException("Cannot encode merged schema for plugin '{$manifest['slug']}'.");
            }

            $stmt = $this->pdo->prepare(
                'UPDATE plugins
                    SET name = :name,
                        version = :version,
                        schema_json = :schema_json::jsonb,
                        schema_version = schema_version + 1,
                        updated_at = NOW()
                  WHERE id = :id
                  RETURNING slug, name, plugin_type, version, status, schema_version, installed_at, updated_at'
            );
            $stmt->execute([
                ':id' => $current['id'],
                ':name' => $manifest['name'],
                ':version' => $manifest['version'],
                ':schema_json' => $schemaJson,
            ]);

            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row === false) {
                throw new PluginException("Failed to persist updated plugin '{$manifest['slug']}'.");
            }

            return $row;
        }

        $stmt = $this->pdo->prepare(
            'UPDATE plugins
                SET name = :name,
                    version = :version,
                    updated_at = NOW()
              WHERE id = :id
              RETURNING slug, name, plugin_type, version, status, schema_version, installed_at, updated_at'
        );
        $stmt->execute([
            ':id' => $current['id'],
            ':name' => $manifest['name'],
            ':version' => $manifest['version'],
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            throw new PluginException("Failed to persist updated plugin '{$manifest['slug']}'.");
        }

        return $row;
    }

    private function updateStatus(string $slug, string $status): void
    {
        $this->pdo->prepare(
            'UPDATE plugins SET status = :status, updated_at = NOW() WHERE slug = :slug'
        )->execute([':status' => $status, ':slug' => $slug]);
    }

    private function loadHooks(string $slug): void
    {
        $hooksPath = $this->pluginsDir . '/' . $slug . '/Hooks.php';

        if (file_exists($hooksPath)) {
            require_once $hooksPath;
        }
    }

    private function requireLifecycleFile(string $slug): void
    {
        $path = $this->pluginsDir . '/' . $slug . '/Lifecycle.php';

        if (file_exists($path)) {
            require_once $path;
        }
    }

    private function instantiateLifecycle(string $slug): ?PluginLifecycleInterface
    {
        $class = self::PLUGIN_NAMESPACE_PREFIX . $slug . '\\Lifecycle';

        if (!class_exists($class)) {
            return null;
        }

        return new $class($this->pdo); // NOSONAR — convention-based plugin lifecycle class
    }

    /**
     * @return object|null
     */
    private function instantiateHooks(string $slug): ?object
    {
        $class = self::PLUGIN_NAMESPACE_PREFIX . $slug . '\\Hooks';

        if (!class_exists($class)) {
            return null;
        }

        return $this->instantiateWithOptionalPdo($class);
    }

    private function instantiateWithOptionalPdo(string $class): object
    {
        $ref = new \ReflectionClass($class);
        $constructor = $ref->getConstructor();

        if ($constructor !== null) {
            foreach ($constructor->getParameters() as $param) {
                $type = $param->getType();
                if ($type instanceof \ReflectionNamedType && $type->getName() === PDO::class) {
                    return new $class($this->pdo); // NOSONAR - convention-based plugin class
                }
            }
        }

        return new $class(); // NOSONAR - convention-based plugin class
    }
}
