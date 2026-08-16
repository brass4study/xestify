<?php

declare(strict_types=1);

namespace Xestify\controllers;

use DomainException;
use Exception;
use InvalidArgumentException;
use OutOfBoundsException;
use Xestify\core\Request;
use Xestify\core\RequestFactory;
use Xestify\core\Response;
use Xestify\core\RuntimePathNormalizer;
use Xestify\exceptions\PluginException;
use Xestify\services\PluginAdministrationService;

/**
 * PluginManagerController — Management endpoints for plugins.
 *
 * Routes (require AuthMiddleware + admin role):
 *   GET    /api/v1/plugins
 *   GET    /api/v1/plugins/available
 *   POST   /api/v1/plugins
 *   POST   /api/v1/plugins/sync
 *   POST   /api/v1/plugins/{slug}/update
 *   POST   /api/v1/plugins/{slug}/rollback
 *   PUT    /api/v1/plugins/{slug}/status
 *   GET    /api/v1/plugins/{slug}/config
 *   PUT    /api/v1/plugins/{slug}/config
 *   POST   /api/v1/plugins/{slug}/move-up
 *   POST   /api/v1/plugins/{slug}/move-down
 *   DELETE /api/v1/plugins/{slug}
 */
class PluginManagerController
{
    public function __construct(
        private PluginAdministrationService $pluginAdministration,
        private ?RequestFactory $requestFactory = null
    ) {
    }

    private const MSG_SLUG_REQUIRED = 'Plugin slug is required.';
    private const MSG_STATUS_REQUIRED = 'Status is required.';
    private const MSG_INVALID_STATUS = 'Status must be "active" or "inactive".';
    private const MSG_ADMIN_REQUIRED = 'Admin role is required.';
    private const MSG_PLUGIN_NOT_FOUND = 'Plugin not found.';
    private const MSG_PLUGIN_UPDATE_FAILED = 'Plugin update failed.';
    private const MSG_PLUGIN_ROLLBACK_FAILED = 'Plugin rollback failed.';
    private const MSG_PLUGIN_CONFIG_FAILED = 'Plugin config update failed.';
    private const MSG_ERROR_PREFIX = 'Error: ';
    private const MSG_PLUGIN_NAME_REQUIRED = 'plugin_name is required.';

    /**
     * GET /api/v1/plugins
     * Returns list of all installed plugins with their status, type, and metadata.
     */
    public function listPlugins(array $params, ?Request $request = null): void
    {
        $request ??= $this->requestFactory()->fromGlobals($params);

        if (!$request->hasRole('admin')) {
            Response::make()->forbidden(self::MSG_ADMIN_REQUIRED);
            return;
        }

        try {
            $plugins = $this->pluginAdministration->listInstalled();
            Response::make()->json(['plugins' => $plugins]);
        } catch (Exception $e) {
            Response::make()->serverError('Database error: ' . $e->getMessage());
        }
    }

    /**
     * GET /api/v1/plugins/available
     * Disk plugin_name folders not yet registered — candidates for manual registration.
     */
    public function listAvailablePlugins(array $params, ?Request $request = null): void
    {
        $request ??= $this->requestFactory()->fromGlobals($params);

        if (!$request->hasRole('admin')) {
            Response::make()->forbidden(self::MSG_ADMIN_REQUIRED);
            return;
        }

        try {
            $available = $this->pluginAdministration->listAvailableForRegistration();
            Response::make()->json(['available' => $available]);
        } catch (Exception $e) {
            Response::make()->serverError(self::MSG_ERROR_PREFIX . $e->getMessage());
        }
    }

    /**
     * POST /api/v1/plugins
     * Manually registers the first instance of a plugin discovered on disk.
     * Body: { "plugin_name": string, "slug"?: string, "name"?: string, "description"?: string, "fields"?: array, "target_entity"?: string }
     */
    public function registerPlugin(array $params, ?Request $request = null): void
    {
        $request ??= $this->requestFactory()->fromGlobals($params);

        if (!$request->hasRole('admin')) {
            Response::make()->forbidden(self::MSG_ADMIN_REQUIRED);
            return;
        }

        $body = $request->allBody();
        $pluginName = (string) ($body['plugin_name'] ?? '');

        if ($pluginName === '') {
            Response::make()->unprocessable(self::MSG_PLUGIN_NAME_REQUIRED, ['plugin_name' => self::MSG_PLUGIN_NAME_REQUIRED]);
            return;
        }

        try {
            $overrides = array_intersect_key($body, array_flip(['slug', 'name', 'description', 'fields', 'target_entity']));
            $plugin = $this->pluginAdministration->registerNew($pluginName, $overrides);
            Response::make()->json(['plugin' => $this->flattenPlugin($plugin)]);
        } catch (InvalidArgumentException | DomainException $e) {
            Response::make()->unprocessable($e->getMessage());
        } catch (Exception $e) {
            Response::make()->serverError(self::MSG_ERROR_PREFIX . $e->getMessage());
        }
    }

    /**
     * PUT /api/v1/plugins/{slug}/status
     * Update plugin status (activate or deactivate).
     * Body: { "status": "active" | "inactive" }
     */
    public function updatePluginStatus(array $params, ?Request $request = null): void
    {
        $request ??= $this->requestFactory()->fromGlobals($params);
        $slug = (string) ($params['slug'] ?? '');

        if (!$request->hasRole('admin')) {
            Response::make()->forbidden(self::MSG_ADMIN_REQUIRED);
            return;
        }

        if ($slug === '') {
            Response::make()->unprocessable(self::MSG_SLUG_REQUIRED, ['slug' => self::MSG_SLUG_REQUIRED]);
            return;
        }

        try {
            $status = $this->extractStatus($request);
            $plugin = $status === 'active'
                ? $this->pluginAdministration->activate($slug)
                : $this->pluginAdministration->deactivate($slug);
            Response::make()->json($this->flattenPlugin($plugin));
        } catch (InvalidArgumentException $e) {
            Response::make()->unprocessable($e->getMessage(), ['status' => $e->getMessage()]);
        } catch (OutOfBoundsException) {
            Response::make()->notFound(self::MSG_PLUGIN_NOT_FOUND);
        } catch (Exception $e) {
            Response::make()->serverError(self::MSG_ERROR_PREFIX . $e->getMessage());
        }
    }

    /**
     * POST /api/v1/plugins/{slug}/move-up
     * Swaps this plugin's display order with its predecessor. No-op when
     * already first.
     */
    public function movePluginUp(array $params, ?Request $request = null): void
    {
        $request ??= $this->requestFactory()->fromGlobals($params);
        $slug = (string) ($params['slug'] ?? '');

        if (!$request->hasRole('admin')) {
            Response::make()->forbidden(self::MSG_ADMIN_REQUIRED);
            return;
        }

        if ($slug === '') {
            Response::make()->unprocessable(self::MSG_SLUG_REQUIRED, ['slug' => self::MSG_SLUG_REQUIRED]);
            return;
        }

        try {
            $plugin = $this->pluginAdministration->moveUp($slug);
            Response::make()->json($this->flattenPlugin($plugin));
        } catch (OutOfBoundsException) {
            Response::make()->notFound(self::MSG_PLUGIN_NOT_FOUND);
        } catch (Exception $e) {
            Response::make()->serverError(self::MSG_ERROR_PREFIX . $e->getMessage());
        }
    }

    /**
     * POST /api/v1/plugins/{slug}/move-down
     * Swaps this plugin's display order with its successor. No-op when
     * already last.
     */
    public function movePluginDown(array $params, ?Request $request = null): void
    {
        $request ??= $this->requestFactory()->fromGlobals($params);
        $slug = (string) ($params['slug'] ?? '');

        if (!$request->hasRole('admin')) {
            Response::make()->forbidden(self::MSG_ADMIN_REQUIRED);
            return;
        }

        if ($slug === '') {
            Response::make()->unprocessable(self::MSG_SLUG_REQUIRED, ['slug' => self::MSG_SLUG_REQUIRED]);
            return;
        }

        try {
            $plugin = $this->pluginAdministration->moveDown($slug);
            Response::make()->json($this->flattenPlugin($plugin));
        } catch (OutOfBoundsException) {
            Response::make()->notFound(self::MSG_PLUGIN_NOT_FOUND);
        } catch (Exception $e) {
            Response::make()->serverError(self::MSG_ERROR_PREFIX . $e->getMessage());
        }
    }

    /**
     * Flat wire-shape for a plugin row — the same keys listPlugins() (SQL
     * aliasing) and getPluginConfig()/savePluginConfig() (PluginConfigService::
     * buildConfigResponse()) already return. registerPlugin() and
     * updatePluginStatus() return PluginAdministrationService's raw row
     * instead (manifest_json nested, not flattened into name/plugin_type/
     * version/description) — without this, the frontend caller only ever
     * sees an empty plugin_type/name for the plugin it just registered or
     * (de)activated.
     *
     * @param array<string, mixed> $plugin
     * @return array<string, mixed>
     */
    private function flattenPlugin(array $plugin): array
    {
        $manifest = is_array($plugin['manifest_json'] ?? null) ? $plugin['manifest_json'] : [];

        return [
            'slug' => (string) ($plugin['slug'] ?? ''),
            'name' => (string) ($manifest['label'] ?? ''),
            'description' => (string) ($manifest['description'] ?? ''),
            'plugin_type' => (string) ($manifest['type'] ?? ''),
            'status' => (string) ($plugin['status'] ?? ''),
            'version' => (string) ($manifest['version'] ?? ''),
            'installed_at' => $plugin['installed_at'] ?? null,
            'updated_at' => $plugin['updated_at'] ?? null,
        ];
    }

    private function extractStatus(Request $request): string
    {
        $body = $request->allBody();
        $status = (string) ($body['status'] ?? '');

        if ($status === '') {
            throw new InvalidArgumentException(self::MSG_STATUS_REQUIRED);
        }

        if (!in_array($status, ['active', 'inactive'], true)) {
            throw new InvalidArgumentException(self::MSG_INVALID_STATUS);
        }

        return $status;
    }

    public function listPluginUpdates(array $params, ?Request $request = null): void
    {
        $request ??= $this->requestFactory()->fromGlobals($params);

        if (!$request->hasRole('admin')) {
            Response::make()->forbidden(self::MSG_ADMIN_REQUIRED);
            return;
        }

        try {
            $outdated = $this->pluginAdministration->getOutdated();
            Response::make()->json(['updates' => $outdated]);
        } catch (\Throwable $e) {
            Response::make()->serverError(self::MSG_ERROR_PREFIX . $e->getMessage());
        }
    }

    /**
     * POST /api/v1/plugins/sync
     * Sync plugins present on disk into the plugins table without consuming updates.
     */
    public function syncPlugins(array $params, ?Request $request = null): void
    {
        $request ??= $this->requestFactory()->fromGlobals($params);

        if (!$request->hasRole('admin')) {
            Response::make()->forbidden(self::MSG_ADMIN_REQUIRED);
            return;
        }

        try {
            $result = $this->pluginAdministration->syncAll();
            Response::make()->json($result);
        } catch (\Throwable $e) {
            Response::make()->serverError(self::MSG_ERROR_PREFIX . $e->getMessage());
        }
    }

    /**
     * POST /api/v1/plugins/{slug}/update
     * Apply an explicit plugin update from disk to the installed runtime state.
     */
    public function updatePlugin(array $params, ?Request $request = null): void
    {
        $request ??= $this->requestFactory()->fromGlobals($params);
        $slug = (string) ($params['slug'] ?? '');

        if (!$request->hasRole('admin')) {
            Response::make()->forbidden(self::MSG_ADMIN_REQUIRED);
            return;
        }

        if ($slug === '') {
            Response::make()->unprocessable(self::MSG_SLUG_REQUIRED, ['slug' => self::MSG_SLUG_REQUIRED]);
            return;
        }

        try {
            $result = $this->pluginAdministration->update($slug);
            Response::make()->json($result);
        } catch (OutOfBoundsException) {
            Response::make()->notFound(self::MSG_PLUGIN_NOT_FOUND);
        } catch (DomainException | PluginException $e) {
            Response::make()->error(409, $e->getMessage());
        } catch (Exception $e) {
            Response::make()->serverError(self::MSG_PLUGIN_UPDATE_FAILED . ' ' . $e->getMessage());
        }
    }

    /**
     * POST /api/v1/plugins/{slug}/rollback
     * Restore plugin runtime state from the latest matching snapshot.
     */
    public function rollbackPlugin(array $params, ?Request $request = null): void
    {
        $request ??= $this->requestFactory()->fromGlobals($params);
        $slug = (string) ($params['slug'] ?? '');

        if (!$request->hasRole('admin')) {
            Response::make()->forbidden(self::MSG_ADMIN_REQUIRED);
            return;
        }

        if ($slug === '') {
            Response::make()->unprocessable(self::MSG_SLUG_REQUIRED, ['slug' => self::MSG_SLUG_REQUIRED]);
            return;
        }

        try {
            $result = $this->pluginAdministration->rollback($slug);
            Response::make()->json($result);
        } catch (OutOfBoundsException) {
            Response::make()->notFound(self::MSG_PLUGIN_NOT_FOUND);
        } catch (DomainException | PluginException $e) {
            Response::make()->error(409, $e->getMessage());
        } catch (Exception $e) {
            Response::make()->serverError(self::MSG_PLUGIN_ROLLBACK_FAILED . ' ' . $e->getMessage());
        }
    }

    /**
     * GET /api/v1/plugins/{slug}/config
     */
    public function getPluginConfig(array $params, ?Request $request = null): void
    {
        $request ??= $this->requestFactory()->fromGlobals($params);
        $slug = (string) ($params['slug'] ?? '');

        if (!$request->hasRole('admin')) {
            Response::make()->forbidden(self::MSG_ADMIN_REQUIRED);
            return;
        }

        if ($slug === '') {
            Response::make()->unprocessable(self::MSG_SLUG_REQUIRED, ['slug' => self::MSG_SLUG_REQUIRED]);
            return;
        }

        try {
            $config = $this->pluginAdministration->getConfig($slug);
            Response::make()->json($config);
        } catch (OutOfBoundsException) {
            Response::make()->notFound(self::MSG_PLUGIN_NOT_FOUND);
        } catch (InvalidArgumentException | DomainException $e) {
            Response::make()->unprocessable($e->getMessage());
        } catch (Exception $e) {
            Response::make()->serverError(self::MSG_ERROR_PREFIX . $e->getMessage());
        }
    }

    /**
     * PUT /api/v1/plugins/{slug}/config
     */
    public function updatePluginConfig(array $params, ?Request $request = null): void
    {
        $request ??= $this->requestFactory()->fromGlobals($params);
        $slug = (string) ($params['slug'] ?? '');

        if (!$request->hasRole('admin')) {
            Response::make()->forbidden(self::MSG_ADMIN_REQUIRED);
            return;
        }

        if ($slug === '') {
            Response::make()->unprocessable(self::MSG_SLUG_REQUIRED, ['slug' => self::MSG_SLUG_REQUIRED]);
            return;
        }

        try {
            $payload = $request->allBody();
            $result = $this->pluginAdministration->saveConfig($slug, is_array($payload) ? $payload : []);
            Response::make()->json($result);
        } catch (OutOfBoundsException) {
            Response::make()->notFound(self::MSG_PLUGIN_NOT_FOUND);
        } catch (InvalidArgumentException | DomainException $e) {
            Response::make()->unprocessable($e->getMessage());
        } catch (Exception $e) {
            Response::make()->serverError(self::MSG_PLUGIN_CONFIG_FAILED . ' ' . $e->getMessage());
        }
    }

    /**
     * DELETE /api/v1/plugins/{slug}
     * Deletes a plugin instance and all its associated data.
     */
    public function deletePlugin(array $params, ?Request $request = null): void
    {
        $request ??= $this->requestFactory()->fromGlobals($params);
        $slug = (string) ($params['slug'] ?? '');

        if (!$request->hasRole('admin')) {
            Response::make()->forbidden(self::MSG_ADMIN_REQUIRED);
            return;
        }

        if ($slug === '') {
            Response::make()->unprocessable(self::MSG_SLUG_REQUIRED, ['slug' => self::MSG_SLUG_REQUIRED]);
            return;
        }

        try {
            $this->pluginAdministration->deletePlugin($slug);
            Response::make()->json(['deleted' => true, 'slug' => $slug]);
        } catch (OutOfBoundsException) {
            Response::make()->notFound(self::MSG_PLUGIN_NOT_FOUND);
        } catch (Exception $e) {
            Response::make()->serverError(self::MSG_ERROR_PREFIX . $e->getMessage());
        }
    }

    private function requestFactory(): RequestFactory
    {
        if ($this->requestFactory === null) {
            $this->requestFactory = new RequestFactory(new RuntimePathNormalizer());
        }

        return $this->requestFactory;
    }
}
