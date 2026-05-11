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
use Xestify\plugins\application\PluginAdministrationService;

/**
 * PluginManagerController — Management endpoints for plugins.
 *
 * Routes (require AuthMiddleware + admin role):
 *   GET    /api/v1/plugins
 *   POST   /api/v1/plugins/sync
 *   POST   /api/v1/plugins/{slug}/update
 *   PUT    /api/v1/plugins/{slug}/status
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
    private const MSG_ERROR_PREFIX = 'Error: ';

    /**
     * GET /api/v1/plugins
     * Returns list of all installed plugins with their status, type, and metadata.
     */
    public function listPlugins(array $params, ?Request $request = null): void
    {
        $request ??= $this->requestFactory()->fromGlobals($params);

        if (!$this->isAdminRequest($request)) {
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
     * PUT /api/v1/plugins/{slug}/status
     * Update plugin status (activate or deactivate).
     * Body: { "status": "active" | "inactive" }
     */
    public function updatePluginStatus(array $params, ?Request $request = null): void
    {
        $request ??= $this->requestFactory()->fromGlobals($params);
        $slug = (string) ($params['slug'] ?? '');

        if (!$this->isAdminRequest($request)) {
            Response::make()->forbidden(self::MSG_ADMIN_REQUIRED);
            return;
        }

        if ($slug === '') {
            Response::make()->unprocessable(self::MSG_SLUG_REQUIRED, ['slug' => self::MSG_SLUG_REQUIRED]);
            return;
        }

        try {
            $status = $this->extractStatus($request);
            $plugin = $this->pluginAdministration->setStatus($slug, $status);
            Response::make()->json($plugin);
        } catch (InvalidArgumentException $e) {
            Response::make()->unprocessable($e->getMessage(), ['status' => $e->getMessage()]);
        } catch (OutOfBoundsException) {
            Response::make()->notFound(self::MSG_PLUGIN_NOT_FOUND);
        } catch (Exception $e) {
            Response::make()->serverError(self::MSG_ERROR_PREFIX . $e->getMessage());
        }
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

    private function isAdminRequest(Request $request): bool
    {
        $user = $request->user();
        if (!is_array($user)) {
            return false;
        }

        $roles = $user['roles'] ?? [];
        return is_array($roles) && in_array('admin', $roles, true);
    }

    public function listPluginUpdates(array $params, ?Request $request = null): void
    {
        $request ??= $this->requestFactory()->fromGlobals($params);

        if (!$this->isAdminRequest($request)) {
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

        if (!$this->isAdminRequest($request)) {
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

        if (!$this->isAdminRequest($request)) {
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

    private function requestFactory(): RequestFactory
    {
        if ($this->requestFactory === null) {
            $this->requestFactory = new RequestFactory(new RuntimePathNormalizer());
        }

        return $this->requestFactory;
    }
}
