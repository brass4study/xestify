<?php

declare(strict_types=1);

namespace Xestify\controllers;

use PDO;
use Throwable;
use Xestify\core\Request;
use Xestify\core\RequestFactory;
use Xestify\core\Response;
use Xestify\core\RuntimePathNormalizer;

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
    private const MSG_NOT_OWNER = 'You do not have permission to modify this item.';

    public function __construct(
        private PDO $pdo,
        private ?RequestFactory $requestFactory = null,
        private ?ExtensionPluginContentService $contentService = null,
        private ?ExtensionPluginDataStore $dataStore = null
    ) {
        $this->contentService ??= new ExtensionPluginContentService($pdo);
        $this->dataStore ??= new ExtensionPluginDataStore($pdo);
    }

    /**
     * GET /api/v1/plugins/{plugin_slug}/{entity}/{id}
     * Returns all items for the given plugin, entity type, and record.
     */
    public function index(array $params, ?Request $request = null): void
    {
        $context = $this->resolveBaseContext($params, $request);
        if (!$this->ensureReadableRequest($context)) {
            return;
        }

        $rows = $this->dataStore->fetchRows($context['plugin_slug'], $context['entity'], $context['record_id']);

        $rows = array_map(fn(array $row) => $this->decodeContent($row), $rows ?: []);
        $rows = $this->attachAuthorNames($rows);

        Response::make()->json($rows, ['total' => count($rows)]);
    }

    /**
     * POST /api/v1/plugins/{plugin_slug}/{entity}/{id}
        * Creates a new item. Content is normalized against persisted extension schema.
     */
    public function create(array $params, ?Request $request = null): void
    {
        $context = $this->resolveBaseContext($params, $request);
        if (!$this->ensureReadableRequest($context)) {
            return;
        }

        $data = $context['request']->allBody();
        if ($data === []) {
            Response::make()->unprocessable(self::MSG_CONTENT_REQUIRED, ['content' => self::MSG_CONTENT_REQUIRED]);
            return;
        }

        $data = $this->contentService->normalizeContentBySchema($context['plugin_slug'], $data, $context['request']);
        $row = $this->dataStore->insertRow($context['plugin_slug'], $context['entity'], $context['record_id'], $data);

        $payload = $row !== false ? $this->decodeContent($row) : [];
        $payload = $this->attachAuthorName($payload);

        Response::make()->status(201)->json($payload);
    }

    /**
     * PUT /api/v1/plugins/{plugin_slug}/{entity}/{id}/{item_id}
        * Merges schema-normalized request body into existing content (JSONB merge).
     */
    public function update(array $params, ?Request $request = null): void
    {
        $context = $this->resolveItemContext($params, $request);
        $data = $context['request']->allBody();

        if (!$this->guardExtensionWriteRequest($context['plugin_slug'], $context['entity'], $context['record_id'], $context['item_id'], $data)) {
            return;
        }

        if (!$this->guardOwnership($context)) {
            return;
        }

        $data = $this->contentService->normalizeContentBySchema($context['plugin_slug'], $data, $context['request'], true);

        $row = $this->dataStore->updateRow(
            $context['item_id'],
            $context['plugin_slug'],
            $context['entity'],
            $context['record_id'],
            $data
        );

        if ($row === false) {
            Response::make()->notFound(self::MSG_ITEM_NOT_FOUND);
            return;
        }

        $payload = $this->decodeContent($row);
        $payload = $this->attachAuthorName($payload);

        Response::make()->json($payload);
    }

    /**
     * DELETE /api/v1/plugins/{plugin_slug}/{entity}/{id}/{item_id}
     * Deletes an item permanently.
     */
    public function delete(array $params, ?Request $request = null): void
    {
        $context = $this->resolveItemContext($params, $request);
        if (!$this->ensureDeleteRequest($context)) {
            return;
        }

        if (!$this->guardOwnership($context)) {
            return;
        }

        $affectedRows = $this->dataStore->deleteRow(
            $context['item_id'],
            $context['plugin_slug'],
            $context['entity'],
            $context['record_id']
        );

        if ($affectedRows === 0) {
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

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array<string, mixed>>
     */
    private function attachAuthorNames(array $rows): array
    {
        $authorIds = [];
        foreach ($rows as $row) {
            $content = is_array($row['content'] ?? null) ? $row['content'] : [];
            $authorId = trim((string) ($content['author_id'] ?? ''));

            if ($authorId !== '') {
                $authorIds[$authorId] = true;
            }
        }

        if ($authorIds === []) {
            return $rows;
        }

        $authors = $this->loadAuthorsById(array_keys($authorIds));
        foreach ($rows as $idx => $row) {
            $content = is_array($row['content'] ?? null) ? $row['content'] : [];
            $authorId = trim((string) ($content['author_id'] ?? ''));
            if ($authorId === '') {
                continue;
            }

            if (isset($authors[$authorId])) {
                $content['author_name'] = $authors[$authorId];
            } else {
                unset($content['author_name']);
            }

            $rows[$idx]['content'] = $content;
        }

        return $rows;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function attachAuthorName(array $row): array
    {
        $rows = $this->attachAuthorNames([$row]);
        return $rows[0] ?? $row;
    }

    /**
     * @param array<int, string> $authorIds
     * @return array<string, string>
     */
    private function loadAuthorsById(array $authorIds): array
    {
        if ($authorIds === []) {
            return [];
        }

        $placeholders = [];
        $params = [];
        foreach (array_values($authorIds) as $idx => $authorId) {
            $key = ':id' . $idx;
            $placeholders[] = $key;
            $params[$key] = $authorId;
        }

        try {
            $stmt = $this->pdo->prepare(
                'SELECT id::text AS id, email
                   FROM users
                  WHERE id IN (' . implode(', ', $placeholders) . ')'
            );
            $stmt->execute($params);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable) {
            return [];
        }

        $authors = [];
        foreach ($rows as $row) {
            $id = trim((string) ($row['id'] ?? ''));
            $email = trim((string) ($row['email'] ?? ''));
            if ($id === '' || $email === '') {
                continue;
            }

            $authors[$id] = $email;
        }

        return $authors;
    }

    private function respondNotFoundIfEmpty(string $value, string $message): bool
    {
        if ($value !== '') {
            return false;
        }
        Response::make()->notFound($message);
        return true;
    }

    /**
     * @param array<string, mixed> $params
     * @return array{request: Request, plugin_slug: string, entity: string, record_id: string}
     */
    private function resolveBaseContext(array $params, ?Request $request = null): array
    {
        $resolvedRequest = $request ?? $this->requestFactory()->fromGlobals($params);

        return [
            'request' => $resolvedRequest,
            'plugin_slug' => (string) ($params['plugin_slug'] ?? ''),
            'entity' => (string) ($params['entity'] ?? ''),
            'record_id' => (string) ($params['id'] ?? ''),
        ];
    }

    /**
     * @param array<string, mixed> $params
     * @return array{request: Request, plugin_slug: string, entity: string, record_id: string, item_id: string}
     */
    private function resolveItemContext(array $params, ?Request $request = null): array
    {
        $context = $this->resolveBaseContext($params, $request);
        $context['item_id'] = (string) ($params['item_id'] ?? '');

        return $context;
    }

    /**
     * @param array{request: Request, plugin_slug: string, entity: string, record_id: string} $context
     */
    private function ensureReadableRequest(array $context): bool
    {
        $hasError = $this->respondNotFoundIfEmpty($context['plugin_slug'], self::MSG_PLUGIN_REQUIRED)
            || $this->respondNotFoundIfEmpty($context['entity'], self::MSG_ENTITY_REQUIRED)
            || $this->respondNotFoundIfEmpty($context['record_id'], self::MSG_RECORD_REQUIRED);

        return !$hasError && $this->guardExtensionRequest($context['plugin_slug'], $context['entity'], $context['record_id']);
    }

    /**
     * @param array{request: Request, plugin_slug: string, entity: string, record_id: string, item_id: string} $context
     */
    private function ensureDeleteRequest(array $context): bool
    {
        $hasError = $this->respondNotFoundIfEmpty($context['plugin_slug'], self::MSG_PLUGIN_REQUIRED)
            || $this->respondNotFoundIfEmpty($context['entity'], self::MSG_ENTITY_REQUIRED)
            || $this->respondNotFoundIfEmpty($context['record_id'], self::MSG_RECORD_REQUIRED)
            || $this->respondNotFoundIfEmpty($context['item_id'], self::MSG_ITEM_REQUIRED);

        return !$hasError && $this->guardExtensionRequest($context['plugin_slug'], $context['entity'], $context['record_id']);
    }

    private function guardExtensionRequest(string $pluginSlug, string $entity, string $recordId): bool
    {
        $isValid = true;

        if ($isValid && !$this->isActiveExtensionPlugin($pluginSlug)) {
            Response::make()->notFound(self::MSG_PLUGIN_NOT_ACTIVE);
            $isValid = false;
        }

        if ($isValid && !$this->contentService->isEntityAllowedByPluginConfig($pluginSlug, $entity)) {
            Response::make()->notFound(self::MSG_PLUGIN_NOT_ACTIVE);
            $isValid = false;
        }

        if ($isValid && !$this->parentRecordExists($entity, $recordId)) {
            Response::make()->notFound(self::MSG_PARENT_NOT_FOUND);
            $isValid = false;
        }

        return $isValid;
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

    /**
     * @param array{request: Request, plugin_slug: string, entity: string, record_id: string, item_id: string} $context
     */
    private function guardOwnership(array $context): bool
    {
        $row = $this->dataStore->findRow($context['item_id'], $context['plugin_slug'], $context['entity'], $context['record_id']);
        if ($row === false) {
            // Let the caller's own lookup return 404 for a missing item.
            return true;
        }

        $content = $this->decodeContent($row)['content'] ?? [];
        $authorId = trim((string) ($content['author_id'] ?? ''));
        if ($authorId === '') {
            // Item type has no authorship concept (or legacy row without it): nothing to guard.
            return true;
        }

        $user = $context['request']->user() ?? [];
        $requesterId = trim((string) ($user['sub'] ?? ''));

        if ($requesterId !== '' && $requesterId === $authorId) {
            return true;
        }

        Response::make()->forbidden(self::MSG_NOT_OWNER);
        return false;
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

    private function requestFactory(): RequestFactory
    {
        if ($this->requestFactory === null) {
            $this->requestFactory = new RequestFactory(new RuntimePathNormalizer());
        }

        return $this->requestFactory;
    }
}

