<?php

declare(strict_types=1);

namespace Xestify\controllers;

use PDO;
use Xestify\core\Database;
use Xestify\core\Request;
use Xestify\core\Response;
use Xestify\exceptions\EntityServiceException;
use Xestify\exceptions\RepositoryException;
use Xestify\exceptions\ValidationException;
use Xestify\plugins\HookDispatcher;
use Xestify\repositories\GenericRepository;
use Xestify\services\EntityService;
use Xestify\services\ValidationService;

/**
 * EntityController — REST endpoints for dynamic entity records.
 *
 * Routes (all require authenticated request via AuthMiddleware):
 *   GET    /api/v1/entities/{slug}/schema
 *   GET    /api/v1/entities/{slug}/records
 *   POST   /api/v1/entities/{slug}/records
 *   GET    /api/v1/entities/{slug}/records/{id}
 *   PUT    /api/v1/entities/{slug}/records/{id}
 *   DELETE /api/v1/entities/{slug}/records/{id}
 */
class EntityController
{
    public function __construct(
        private EntityService $service,
        private PDO $pdo,
        private HookDispatcher $hookDispatcher = new HookDispatcher()
    ) {
    }

    private const MSG_SLUG_REQUIRED      = 'Entity slug is required.';
    private const MSG_RECORD_ID_REQUIRED = 'Record id is required.';

    /**
     * GET /api/v1/entities
     * Returns all active entity types with their latest schema.
     */
    public function listEntities(array $params, ?Request $request = null): void
    {
        $request ??= Request::fromGlobals($params);

        $stmt = $this->pdo->prepare(
            'SELECT se.slug, se.name AS label,
                    em.schema_json, em.schema_version
             FROM system_entities se
             LEFT JOIN LATERAL (
                 SELECT schema_json, schema_version
                 FROM entity_metadata
                 WHERE entity_slug = se.slug
                 ORDER BY schema_version DESC
                 LIMIT 1
             ) em ON true
             WHERE se.is_active = true
             ORDER BY se.name ASC'
        );
        $stmt->execute();
        $rows = $stmt->fetchAll();

        $entities = [];
        foreach ($rows as $row) {
            $schema = json_decode((string) ($row['schema_json'] ?? '{}'), true);
            $fields = is_array($schema) && isset($schema['fields']) ? $schema['fields'] : [];
            $singularLabel = null;
            if (is_array($schema) && isset($schema['label_singular']) && is_string($schema['label_singular'])) {
                $singularLabel = $schema['label_singular'];
            }

            $entities[] = [
                'slug'           => $row['slug'],
                'label'          => $row['label'],
                'label_singular' => $singularLabel,
                'schema_version' => (int) ($row['schema_version'] ?? 1),
                'fields'         => $fields,
            ];
        }

        Response::make()->json($entities);
    }

    /**
     * GET /api/v1/entities/{slug}/schema
     * Returns the latest schema_json for the entity type.
     */
    public function schema(array $params, ?Request $request = null): void
    {
        $request ??= Request::fromGlobals($params);
        $slug = (string) ($params['slug'] ?? '');

        if ($slug === '') {
              Response::make()->notFound(self::MSG_SLUG_REQUIRED);
            return;
        }

        $stmt = $this->pdo->prepare(
            'SELECT schema_json, schema_version FROM entity_metadata
             WHERE entity_slug = :slug
             ORDER BY schema_version DESC
             LIMIT 1'
        );
        $stmt->execute([':slug' => $slug]);
        $row = $stmt->fetch();

        if ($row === false) {
            Response::make()->notFound('No schema found for entity: ' . $slug);
            return;
        }

        $decoded = json_decode((string) $row['schema_json'], true);

        Response::make()->json([
            'entity_slug'    => $slug,
            'schema_version' => (int) $row['schema_version'],
            'schema'         => is_array($decoded) ? $decoded : [],
        ]);
    }

    /**
     * GET /api/v1/entities/{slug}/records
     * Returns all active records for the entity type.
     */
    public function index(array $params, ?Request $request = null): void
    {
        $request ??= Request::fromGlobals($params);
        $slug = (string) ($params['slug'] ?? '');

        if ($slug === '') {
              Response::make()->notFound(self::MSG_SLUG_REQUIRED);
            return;
        }

        $rows = $this->service->listRecords($slug);

        Response::make()->json($rows, ['total' => count($rows)]);
    }

    /**
     * POST /api/v1/entities/{slug}/records
     * Creates a new record. Body is validated against the entity schema.
     */
    public function create(array $params, ?Request $request = null): void
    {
        $request ??= Request::fromGlobals($params);
        $slug    = (string) ($params['slug'] ?? '');
        $data    = $request->allBody();
        $ownerId = $this->resolveOwnerId($request);

        if ($slug === '') {
              Response::make()->notFound(self::MSG_SLUG_REQUIRED);
            return;
        }

        try {
            $record = $this->service->createRecord($slug, $data, $ownerId);
        } catch (ValidationException $e) {
            Response::make()->unprocessable('Validation failed.', $e->getErrors());
            return;
        } catch (EntityServiceException $e) {
            Response::make()->notFound($e->getMessage());
            return;
        }

        Response::make()->status(201)->json($record);
    }

    /**
     * GET /api/v1/entities/{slug}/records/{id}
     * Returns a single active record by UUID.
     */
    public function show(array $params, ?Request $request = null): void
    {
        $request ??= Request::fromGlobals($params);
        $id      = (string) ($params['id'] ?? '');

        if ($id === '') {
              Response::make()->notFound(self::MSG_RECORD_ID_REQUIRED);
            return;
        }

        $record = $this->service->getRecord($id);

        if ($record === null) {
            Response::make()->notFound('Record not found: ' . $id);
            return;
        }

        Response::make()->json($record);
    }

    /**
     * PUT /api/v1/entities/{slug}/records/{id}
     * Partially updates a record's content (JSONB merge).
     */
    public function update(array $params, ?Request $request = null): void
    {
        $request ??= Request::fromGlobals($params);
        $slug    = (string) ($params['slug'] ?? '');
        $id      = (string) ($params['id'] ?? '');
        $data    = $request->allBody();

        if ($id === '') {
                Response::make()->notFound(self::MSG_RECORD_ID_REQUIRED);
            return;
        }

        try {
            $record = $this->service->updateRecord($id, $slug, $data);
        } catch (ValidationException $e) {
            Response::make()->unprocessable('Validation failed.', $e->getErrors());
            return;
            } catch (EntityServiceException | RepositoryException $e) {
            Response::make()->notFound($e->getMessage());
            return;
        }

        Response::make()->json($record);
    }

    /**
     * DELETE /api/v1/entities/{slug}/records/{id}
     * Soft-deletes a record (sets deleted_at = NOW()).
     */
    public function destroy(array $params, ?Request $request = null): void
    {
        $request ??= Request::fromGlobals($params);
        $id = (string) ($params['id'] ?? '');

        if ($id === '') {
              Response::make()->notFound(self::MSG_RECORD_ID_REQUIRED);
            return;
        }

        try {
            $this->service->deleteRecord($id);
        } catch (RepositoryException $e) {
            Response::make()->notFound($e->getMessage());
            return;
        }

        Response::make()->json(['deleted' => true, 'id' => $id]);
    }

    /**
     * GET /api/v1/entities/{slug}/tabs
     * Returns the list of tabs registered by plugins for this entity.
     * Calls applyFilter('registerTabs') to collect plugin contributions.
     */
    public function tabs(array $params, ?Request $request = null): void
    {
        $slug = (string) ($params['slug'] ?? '');

        if ($slug === '') {
            Response::make()->notFound(self::MSG_SLUG_REQUIRED);
            return;
        }

        $tabs = $this->hookDispatcher->applyFilter('registerTabs', [], ['entity' => $slug]);

        Response::make()->json(['tabs' => $tabs, 'entity' => $slug]);
    }

    /**
     * GET /api/v1/entities/{slug}/actions
     * Returns the list of row actions registered by plugins for this entity.
     * Calls applyFilter('registerActions') to collect plugin contributions.
     */
    public function actions(array $params, ?Request $request = null): void
    {
        $slug = (string) ($params['slug'] ?? '');

        if ($slug === '') {
            Response::make()->notFound(self::MSG_SLUG_REQUIRED);
            return;
        }

        $actions = $this->hookDispatcher->applyFilter('registerActions', [], ['entity' => $slug]);

        Response::make()->json(['actions' => $actions, 'entity' => $slug]);
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Extract the authenticated user's ID from the request, if available.
     */
    private function resolveOwnerId(Request $request): ?string
    {
        $user = $request->user();
        if (!is_array($user)) {
            return null;
        }
        $sub = $user['sub'] ?? null;
        return is_string($sub) ? $sub : null;
    }
}
