<?php

declare(strict_types=1);

namespace Xestify\controllers;

use Exception;
use InvalidArgumentException;
use PDO;
use PDOException;
use Xestify\core\Request;
use Xestify\core\Response;

/**
 * PluginManagerController — Management endpoints for plugins.
 *
 * Routes (require AuthMiddleware + admin role):
 *   GET    /api/v1/plugins
 *   PUT    /api/v1/plugins/{slug}/status
 */
class PluginManagerController
{
    public function __construct(private PDO $pdo)
    {
    }

    private const MSG_SLUG_REQUIRED = 'Plugin slug is required.';
    private const MSG_STATUS_REQUIRED = 'Status is required.';
    private const MSG_INVALID_STATUS = 'Status must be "active" or "inactive".';
    private const MSG_ADMIN_REQUIRED = 'Admin role is required.';
    private const MSG_PLUGIN_NOT_FOUND = 'Plugin not found.';

    /**
     * GET /api/v1/plugins
     * Returns list of all installed plugins with their status, type, and metadata.
     */
    public function listPlugins(array $params, ?Request $request = null): void
    {
        $request ??= Request::fromGlobals($params);

        if (!$this->isAdminRequest($request)) {
            Response::make()->forbidden(self::MSG_ADMIN_REQUIRED);
            return;
        }

        try {
            $stmt = $this->pdo->prepare(
                'SELECT slug, name, plugin_type, version, status, schema_version, installed_at, updated_at
                 FROM plugins
                 ORDER BY slug ASC'
            );
            $stmt->execute();
            $plugins = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            Response::make()->json(['plugins' => $plugins]);
        } catch (PDOException $e) {
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
        $request ??= Request::fromGlobals($params);
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

            if (!$this->pluginExists($slug)) {
                Response::make()->notFound(self::MSG_PLUGIN_NOT_FOUND);
            } else {
                $plugin = $this->persistPluginStatus($slug, $status);
                if (!$plugin) {
                    Response::make()->serverError('Failed to update plugin status.');
                } else {
                    Response::make()->json($plugin);
                }
            }
        } catch (InvalidArgumentException $e) {
            Response::make()->unprocessable($e->getMessage(), ['status' => $e->getMessage()]);
        } catch (Exception $e) {
            Response::make()->serverError('Error: ' . $e->getMessage());
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

    private function pluginExists(string $slug): bool
    {
        $stmt = $this->pdo->prepare('SELECT id FROM plugins WHERE slug = :slug');
        $stmt->execute([':slug' => $slug]);
        return (bool) $stmt->fetch();
    }

    private function persistPluginStatus(string $slug, string $status): array|false
    {
        $stmt = $this->pdo->prepare(
            'UPDATE plugins SET status = :status, updated_at = NOW()
             WHERE slug = :slug
             RETURNING slug, name, plugin_type, version, status, schema_version, installed_at, updated_at'
        );
        $stmt->execute([
            ':status' => $status,
            ':slug'   => $slug,
        ]);

        return $stmt->fetch(PDO::FETCH_ASSOC);
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
}
