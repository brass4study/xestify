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
    public function __construct(private EntityService $service, private PDO $pdo)
    {
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
            Response::make()->notFound('Entity slug is required.');
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
            Response::make()->notFound('Entity slug is required.');
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
            Response::make()->notFound('Entity slug is required.');
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
            Response::make()->notFound('Record id is required.');
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
            Response::make()->notFound('Record id is required.');
            return;
        }

        try {
            $record = $this->service->updateRecord($id, $slug, $data);
        } catch (ValidationException $e) {
            Response::make()->unprocessable('Validation failed.', $e->getErrors());
            return;
        } catch (EntityServiceException $e) {
            Response::make()->notFound($e->getMessage());
            return;
        } catch (RepositoryException $e) {
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
            Response::make()->notFound('Record id is required.');
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
