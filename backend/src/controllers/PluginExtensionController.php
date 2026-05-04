<?php

declare(strict_types=1);

namespace Xestify\controllers;

use PDO;
use Xestify\core\Request;
use Xestify\core\Response;

/**
 * PluginExtensionController — generic REST endpoints for extension plugin data.
 *
 * Routes (all require authenticated request via AuthMiddleware):
 *   GET    /api/v1/plugins/{plugin_slug}/{entity}/{id}
 *   POST   /api/v1/plugins/{plugin_slug}/{entity}/{id}
 *   PUT    /api/v1/plugins/{plugin_slug}/{entity}/{id}/{item_id}
 *   DELETE /api/v1/plugins/{plugin_slug}/{entity}/{id}/{item_id}
 *
 * Data is stored generically in plugin_extension_data (plugin_slug column
 * discriminates between extension types, just as entity slug discriminates
 * between entity types in EntityController).
 */
class PluginExtensionController
{
    private const MSG_PLUGIN_REQUIRED  = 'Plugin slug is required.';
    private const MSG_ENTITY_REQUIRED  = 'Entity slug is required.';
    private const MSG_RECORD_REQUIRED  = 'Record id is required.';
    private const MSG_CONTENT_REQUIRED = 'Content is required.';
    private const MSG_ITEM_REQUIRED    = 'Item id is required.';
    private const MSG_ITEM_NOT_FOUND   = 'Item not found.';
    private const MSG_PLUGIN_NOT_ACTIVE = 'Extension plugin is not active.';
    private const MSG_PARENT_NOT_FOUND = 'Parent entity record not found.';

    public function __construct(private PDO $pdo)
    {
    }

    /**
     * GET /api/v1/plugins/{plugin_slug}/{entity}/{id}
     * Returns all items for the given plugin, entity type, and record.
     */
    public function index(array $params, ?Request $request = null): void
    {
        $request    ??= Request::fromGlobals($params);
        $pluginSlug = (string) ($params['plugin_slug'] ?? '');
        $entity     = (string) ($params['entity'] ?? '');
        $recordId   = (string) ($params['id'] ?? '');

        $hasError = $this->respondNotFoundIfEmpty($pluginSlug, self::MSG_PLUGIN_REQUIRED)
            || $this->respondNotFoundIfEmpty($entity, self::MSG_ENTITY_REQUIRED)
            || $this->respondNotFoundIfEmpty($recordId, self::MSG_RECORD_REQUIRED);
        if ($hasError) {
            return;
        }

        if (!$this->guardExtensionRequest($pluginSlug, $entity, $recordId)) {
            return;
        }

        $stmt = $this->pdo->prepare(
            'SELECT id, plugin_slug, entity_slug, record_id, content, created_at
               FROM plugin_extension_data
              WHERE plugin_slug = :plugin
                AND entity_slug = :entity
                AND record_id   = :record_id
              ORDER BY created_at ASC'
        );
        $stmt->execute([
            ':plugin'    => $pluginSlug,
            ':entity'    => $entity,
            ':record_id' => $recordId,
        ]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $rows = array_map(fn(array $row) => $this->decodeContent($row), $rows ?: []);

        Response::make()->json($rows, ['total' => count($rows)]);
    }

    /**
     * POST /api/v1/plugins/{plugin_slug}/{entity}/{id}
     * Creates a new item. The request body is stored verbatim as content.
     */
    public function create(array $params, ?Request $request = null): void
    {
        $request    ??= Request::fromGlobals($params);
        $pluginSlug = (string) ($params['plugin_slug'] ?? '');
        $entity     = (string) ($params['entity'] ?? '');
        $recordId   = (string) ($params['id'] ?? '');
        $data       = $request->allBody();

        $hasError = $this->respondNotFoundIfEmpty($pluginSlug, self::MSG_PLUGIN_REQUIRED)
            || $this->respondNotFoundIfEmpty($entity, self::MSG_ENTITY_REQUIRED)
            || $this->respondNotFoundIfEmpty($recordId, self::MSG_RECORD_REQUIRED);
        if ($hasError) {
            return;
        }

        if (!$this->guardExtensionRequest($pluginSlug, $entity, $recordId)) {
            return;
        }

        if ($data === []) {
            Response::make()->unprocessable(self::MSG_CONTENT_REQUIRED, ['content' => self::MSG_CONTENT_REQUIRED]);
            return;
        }

        $content = json_encode($data);

        $stmt = $this->pdo->prepare(
            'INSERT INTO plugin_extension_data (plugin_slug, entity_slug, record_id, content)
             VALUES (:plugin, :entity, :record_id, :content)
             RETURNING id, plugin_slug, entity_slug, record_id, content, created_at'
        );
        $stmt->execute([
            ':plugin'    => $pluginSlug,
            ':entity'    => $entity,
            ':record_id' => $recordId,
            ':content'   => $content !== false ? $content : '{}',
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        Response::make()->status(201)->json($row !== false ? $this->decodeContent($row) : []);
    }

    /**
     * PUT /api/v1/plugins/{plugin_slug}/{entity}/{id}/{item_id}
     * Merges the request body into the existing content (JSONB merge).
     */
    public function update(array $params, ?Request $request = null): void
    {
        $request    ??= Request::fromGlobals($params);
        $pluginSlug = (string) ($params['plugin_slug'] ?? '');
        $entity     = (string) ($params['entity'] ?? '');
        $recordId   = (string) ($params['id'] ?? '');
        $itemId     = (string) ($params['item_id'] ?? '');
        $data       = $request->allBody();

        if (!$this->guardExtensionWriteRequest($pluginSlug, $entity, $recordId, $itemId, $data)) {
            return;
        }

        $content = json_encode($data);

        $stmt = $this->pdo->prepare(
            'UPDATE plugin_extension_data
                SET content = content || :content::jsonb
              WHERE id          = :item_id
                AND plugin_slug = :plugin
                AND entity_slug = :entity
                AND record_id   = :record_id
            RETURNING id, plugin_slug, entity_slug, record_id, content, created_at'
        );
        $stmt->execute([
            ':content'   => $content !== false ? $content : '{}',
            ':item_id'   => $itemId,
            ':plugin'    => $pluginSlug,
            ':entity'    => $entity,
            ':record_id' => $recordId,
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            Response::make()->notFound(self::MSG_ITEM_NOT_FOUND);
            return;
        }

        Response::make()->json($this->decodeContent($row));
    }

    /**
     * DELETE /api/v1/plugins/{plugin_slug}/{entity}/{id}/{item_id}
     * Deletes an item permanently.
     */
    public function delete(array $params, ?Request $request = null): void
    {
        $request    ??= Request::fromGlobals($params);
        $pluginSlug = (string) ($params['plugin_slug'] ?? '');
        $entity     = (string) ($params['entity'] ?? '');
        $recordId   = (string) ($params['id'] ?? '');
        $itemId     = (string) ($params['item_id'] ?? '');

        $hasError = $this->respondNotFoundIfEmpty($pluginSlug, self::MSG_PLUGIN_REQUIRED)
            || $this->respondNotFoundIfEmpty($entity, self::MSG_ENTITY_REQUIRED)
            || $this->respondNotFoundIfEmpty($recordId, self::MSG_RECORD_REQUIRED)
            || $this->respondNotFoundIfEmpty($itemId, self::MSG_ITEM_REQUIRED);
        if ($hasError) {
            return;
        }

        if (!$this->guardExtensionRequest($pluginSlug, $entity, $recordId)) {
            return;
        }

        $stmt = $this->pdo->prepare(
            'DELETE FROM plugin_extension_data
              WHERE id          = :item_id
                AND plugin_slug = :plugin
                AND entity_slug = :entity
                AND record_id   = :record_id'
        );
        $stmt->execute([
            ':item_id'   => $itemId,
            ':plugin'    => $pluginSlug,
            ':entity'    => $entity,
            ':record_id' => $recordId,
        ]);

        if ($stmt->rowCount() === 0) {
            Response::make()->notFound(self::MSG_ITEM_NOT_FOUND);
            return;
        }

        Response::make()->json(['deleted' => true]);
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Decode the content JSONB column from a database row into an array.
     * PostgreSQL returns JSONB as a JSON string; this converts it back.
     */
    private function decodeContent(array $row): array
    {
        $raw = $row['content'] ?? '{}';
        $row['content'] = json_decode((string) $raw, true) ?? [];
        return $row;
    }

    private function respondNotFoundIfEmpty(string $value, string $message): bool
    {
        if ($value !== '') {
            return false;
        }
        Response::make()->notFound($message);
        return true;
    }

    private function guardExtensionRequest(string $pluginSlug, string $entity, string $recordId): bool
    {
        if (!$this->isActiveExtensionPlugin($pluginSlug)) {
            Response::make()->notFound(self::MSG_PLUGIN_NOT_ACTIVE);
            return false;
        }

        if (!$this->parentRecordExists($entity, $recordId)) {
            Response::make()->notFound(self::MSG_PARENT_NOT_FOUND);
            return false;
        }

        return true;
    }

    private function guardExtensionWriteRequest(
        string $pluginSlug,
        string $entity,
        string $recordId,
        string $itemId,
        array $data
    ): bool {
        $isValid = true;
        $hasMissingParams = $this->respondNotFoundIfEmpty($pluginSlug, self::MSG_PLUGIN_REQUIRED)
            || $this->respondNotFoundIfEmpty($entity, self::MSG_ENTITY_REQUIRED)
            || $this->respondNotFoundIfEmpty($recordId, self::MSG_RECORD_REQUIRED)
            || $this->respondNotFoundIfEmpty($itemId, self::MSG_ITEM_REQUIRED);

        if ($hasMissingParams) {
            $isValid = false;
        } elseif (!$this->guardExtensionRequest($pluginSlug, $entity, $recordId)) {
            $isValid = false;
        } elseif ($data === []) {
            Response::make()->unprocessable(self::MSG_CONTENT_REQUIRED, ['content' => self::MSG_CONTENT_REQUIRED]);
            $isValid = false;
        }

        return $isValid;
    }

    private function isActiveExtensionPlugin(string $pluginSlug): bool
    {
        $stmt = $this->pdo->prepare(
            "SELECT 1 FROM plugins
              WHERE slug = :slug
                AND plugin_type = 'extension'
                AND status = 'active'
              LIMIT 1"
        );
        $stmt->execute([':slug' => $pluginSlug]);

        return $stmt->fetchColumn() !== false;
    }

    private function parentRecordExists(string $entity, string $recordId): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT 1 FROM plugin_entity_data
              WHERE id = :id
                AND entity_slug = :entity
                AND deleted_at IS NULL
              LIMIT 1'
        );
        $stmt->execute([':id' => $recordId, ':entity' => $entity]);

        return $stmt->fetchColumn() !== false;
    }
}

