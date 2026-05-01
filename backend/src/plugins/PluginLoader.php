<?php

declare(strict_types=1);

namespace Xestify\plugins;

use PDO;
use Xestify\exceptions\PluginException;

/**
 * PluginLoader — discovers, validates and registers backend plugins.
 *
 * Each plugin lives in a subdirectory of $pluginsDir and must contain:
 *   - manifest.json  Required fields: slug, name, version, type, core_version
 *   - Hooks.php      Optional — loaded via require_once when present
 *
 * Compatibility rule: plugin's core_version must be <= current CORE_VERSION.
 */
class PluginLoader
{
    public const CORE_VERSION = '1.0.0';

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
        $this->registerPlugin($manifest);
        $this->loadHooks($slug);

        return $manifest;
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

    private function registerPlugin(array $manifest): void
    {
        $slug = $manifest['slug'];

        $stmt = $this->pdo->prepare(
            'SELECT id FROM plugins_registry WHERE plugin_slug = :slug'
        );
        $stmt->execute([':slug' => $slug]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($existing !== false) {
            $update = $this->pdo->prepare(
                'UPDATE plugins_registry
                    SET version = :version, updated_at = NOW()
                  WHERE plugin_slug = :slug'
            );
            $update->execute([':version' => $manifest['version'], ':slug' => $slug]);
            return;
        }

        $insert = $this->pdo->prepare(
            'INSERT INTO plugins_registry (plugin_slug, plugin_type, version, status)
             VALUES (:slug, :type, :version, :status)'
        );
        $insert->execute([
            ':slug'    => $slug,
            ':type'    => $manifest['type'],
            ':version' => $manifest['version'],
            ':status'  => 'inactive',
        ]);
    }

    private function loadHooks(string $slug): void
    {
        $hooksPath = $this->pluginsDir . '/' . $slug . '/Hooks.php';

        if (file_exists($hooksPath)) {
            require_once $hooksPath;
        }
    }
}
