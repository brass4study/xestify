<?php

declare(strict_types=1);

namespace Xestify\plugins;

use PDO;
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
 */
class PluginLoader
{
    public const CORE_VERSION = '1.0.0';
    private const PLUGIN_NAMESPACE_PREFIX = 'Xestify\\plugins\\';

    private const MANIFEST_REQUIRED_FIELDS = ['slug', 'name', 'version', 'type', 'core_version'];

    private const VALID_TYPES = ['entity', 'extension'];

    private string $pluginsDir;

    private PDO $pdo;

    /**
     * @param string $pluginsDir  Absolute path to the plugins directory.
     * @param PDO    $pdo         Database connection for registry operations.
     */
    public function __construct(string $pluginsDir, PDO $pdo)
    {
        $this->pluginsDir = rtrim($pluginsDir, '/\\');
        $this->pdo = $pdo;
    }

    // -------------------------------------------------------------------------
    // Public API
    // -------------------------------------------------------------------------

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
     * Load a single plugin: read its manifest, validate compatibility,
     * register (or update) it in plugins_registry, and require Hooks.php.
     *
     * @param  string $slug
     * @return array  Parsed manifest data
     * @throws PluginException
     */
    public function load(string $slug): array
    {
        $manifest = $this->readManifest($slug);
        $this->validateCompatibility($manifest);
        $this->validateDependencies($manifest);
        $isNew = $this->registerPlugin($manifest);
        $this->loadHooks($slug);
        $this->requireLifecycleFile($slug);

        if ($isNew) {
            $lifecycle = $this->instantiateLifecycle($slug);
            if ($lifecycle !== null) {
                $lifecycle->onInstall();
            }
        }

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
     * Load all discovered plugins and return slug => manifest map.
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

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

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
        if (version_compare($manifest['core_version'], self::CORE_VERSION, '>')) {
            throw new PluginException(
                "Plugin '{$manifest['slug']}' requires core >= {$manifest['core_version']}, "
                . 'current core is ' . self::CORE_VERSION
            );
        }
    }

    /**
     * Validate that all plugins listed in manifest 'requires' are already registered
     * in plugins_registry with a sufficient version.
     *
     * Expected format in manifest.json (optional):
     *   "requires": [
     *     {"slug": "other_plugin", "version": "1.0.0"}
     *   ]
     *
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

            $stmt = $this->pdo->prepare(
                'SELECT version FROM plugins WHERE slug = :slug'
            );
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
     * Register or update the plugin in plugins_registry.
     *
     * @return bool true when the plugin is brand-new (INSERT), false on UPDATE
     */
    private function registerPlugin(array $manifest): bool
    {
        $slug = $manifest['slug'];
        $schema = $this->readEntitySchema($manifest);

        $stmt = $this->pdo->prepare(
            'SELECT id FROM plugins WHERE slug = :slug'
        );
        $stmt->execute([':slug' => $slug]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($existing !== false) {
            $this->pdo->prepare(
                'UPDATE plugins
                    SET name = :name,
                        plugin_type = :type,
                        version = :version,
                        schema_json = COALESCE(:schema_json::jsonb, schema_json),
                        schema_version = CASE
                            WHEN :schema_check::jsonb IS NOT NULL
                             AND schema_json IS DISTINCT FROM :schema_compare::jsonb
                            THEN schema_version + 1
                            ELSE schema_version
                        END,
                        updated_at = NOW()
                  WHERE slug = :slug'
            )->execute([
                ':name' => $manifest['name'],
                ':type' => $manifest['type'],
                ':version' => $manifest['version'],
                ':schema_json' => $schema,
                ':schema_check' => $schema,
                ':schema_compare' => $schema,
                ':slug' => $slug,
            ]);

            return false;
        }

        $this->pdo->prepare(
            'INSERT INTO plugins (slug, name, plugin_type, version, status, schema_json, schema_version)
             VALUES (:slug, :name, :type, :version, :status, :schema::jsonb, 1)'
        )->execute([
            ':slug'    => $slug,
            ':name'    => $manifest['name'],
            ':type'    => $manifest['type'],
            ':version' => $manifest['version'],
            ':status'  => 'inactive',
            ':schema'  => $schema,
        ]);

        return true;
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

    // -------------------------------------------------------------------------
    // Boot-time hook registration
    // -------------------------------------------------------------------------

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
     * Instantiate the Hooks class for a plugin.
     *
     * Inspects the constructor via reflection: if a PDO parameter is declared,
     * injects $this->pdo; otherwise instantiates with no arguments.
     * Convention: every plugin's Hooks class must have either a no-arg
     * constructor or a constructor whose first (and only) parameter is typed PDO.
     *
     * @return object|null  An instance with a register(HookDispatcher): void method, or null.
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
        $ref         = new \ReflectionClass($class);
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
