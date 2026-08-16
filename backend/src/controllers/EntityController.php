<?php

declare(strict_types=1);

namespace Xestify\controllers;

use InvalidArgumentException;
use PDO;
use Xestify\core\Request;
use Xestify\core\RequestFactory;
use Xestify\core\Response;
use Xestify\core\RuntimePathNormalizer;
use Xestify\exceptions\EntityServiceException;
use Xestify\exceptions\HookException;
use Xestify\exceptions\RepositoryException;
use Xestify\exceptions\ValidationException;
use Xestify\core\HookDispatcher;
use Xestify\plugins\schema\ReverseRelationTabResolver;
use Xestify\services\EntityService;

/**
 * EntityController — REST endpoints for dynamic entity records.
 *
 * Routes (all require authenticated request via AuthMiddleware):
 *   GET    /api/v1/entities/{slug}/schema
 *   GET    /api/v1/entities/{slug}/options
 *   GET    /api/v1/entities/{slug}/records
 *   POST   /api/v1/entities/{slug}/records
 *   GET    /api/v1/entities/{slug}/records/{id}
 *   PUT    /api/v1/entities/{slug}/records/{id}
 *   DELETE /api/v1/entities/{slug}/records/{id}
 */
class EntityController
{
    private const DEFAULT_PAGE_SIZE = 20;
    private const MAX_PAGE_SIZE = 200;
    public function __construct(
        private EntityService $service,
        private PDO $pdo,
        private HookDispatcher $hookDispatcher,
        private ReverseRelationTabResolver $reverseRelationTabResolver,
        private ?RequestFactory $requestFactory = null
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
        $request ??= $this->requestFactory()->fromGlobals($params);

        $stmt = $this->pdo->prepare(
            "SELECT p.slug, p.manifest_json, p.schema_json
             FROM plugins p
             WHERE p.manifest_json->>'type' = :plugin_type
                 AND p.status = :status
                 AND p.schema_json IS NOT NULL
             ORDER BY p.manifest_json->>'label' ASC"
        );
        $stmt->execute([':plugin_type' => 'entity', ':status' => 'active']);
        $rows = $stmt->fetchAll();

        $entities = [];
        foreach ($rows as $row) {
            $manifest = json_decode((string) ($row['manifest_json'] ?? '{}'), true);
            $manifest = is_array($manifest) ? $manifest : [];
            $schemaConfig = $this->extractEntitySchemaConfig($row);

            $entities[] = [
                'slug'           => $row['slug'],
                'label'          => (string) ($manifest['label'] ?? ''),
                'label_singular' => isset($manifest['label_singular']) && is_string($manifest['label_singular'])
                    ? $manifest['label_singular']
                    : null,
                'fields'         => $schemaConfig['fields'],
                'custom_fields'  => $schemaConfig['custom_fields'],
                'ui_field_order' => $schemaConfig['ui_field_order'],
                'identities'     => $schemaConfig['identities'],
            ];
        }

        Response::make()->json($entities);
    }

    /**
     * @param array<string, mixed> $row
     * @return array{fields: array<mixed>, custom_fields: array<mixed>, ui_field_order: array<mixed>, identities: array<mixed>}
     */
    private function extractEntitySchemaConfig(array $row): array
    {
        $decoded = json_decode((string) ($row['schema_json'] ?? '{}'), true);
        $schema = is_array($decoded) ? $decoded : [];

        $fields = isset($schema['fields']) && is_array($schema['fields']) ? $schema['fields'] : [];
        $customFields = isset($schema['custom_fields']) && is_array($schema['custom_fields']) ? $schema['custom_fields'] : [];
        $uiFieldOrder = isset($schema['ui_field_order']) && is_array($schema['ui_field_order']) ? $schema['ui_field_order'] : [];
        $identities = isset($schema['identities']) && is_array($schema['identities']) ? $schema['identities'] : [];

        return [
            'fields' => $fields,
            'custom_fields' => $customFields,
            'ui_field_order' => $uiFieldOrder,
            'identities' => $identities,
        ];
    }

    /**
     * GET /api/v1/entities/{slug}/schema
     * Returns the latest schema_json for the entity type.
     */
    public function schema(array $params, ?Request $request = null): void
    {
        $request ??= $this->requestFactory()->fromGlobals($params);
        $slug = (string) ($params['slug'] ?? '');

        if ($slug === '') {
            Response::make()->notFound(self::MSG_SLUG_REQUIRED);
            return;
        }

        $stmt = $this->pdo->prepare(
            'SELECT schema_json FROM plugins
             WHERE slug = :slug
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
            'schema'         => is_array($decoded) ? $decoded : [],
        ]);
    }

    /**
     * GET /api/v1/entities/{slug}/options
     * Lightweight {id, label} pairs for this entity's records, used to
     * populate relation <select> pickers on other entities' forms.
     */
    public function options(array $params, ?Request $request = null): void
    {
        $request ??= $this->requestFactory()->fromGlobals($params);
        $slug = (string) ($params['slug'] ?? '');

        if ($slug === '') {
            Response::make()->notFound(self::MSG_SLUG_REQUIRED);
            return;
        }

        try {
            $options = $this->service->listOptions($slug);
        } catch (EntityServiceException $e) {
            Response::make()->notFound($e->getMessage());
            return;
        }

        Response::make()->json($options);
    }

    /**
     * GET /api/v1/entities/{slug}/records
    * Returns a page of active records for the entity type.
     */
    public function index(array $params, ?Request $request = null): void
    {
        $request ??= $this->requestFactory()->fromGlobals($params);
        $slug = (string) ($params['slug'] ?? '');

        if ($slug === '') {
            Response::make()->notFound(self::MSG_SLUG_REQUIRED);
            return;
        }

        $field = trim((string) $request->query('field', ''));
        if ($field !== '') {
            $this->indexByField($slug, $field, (string) $request->query('value', ''));
            return;
        }

        $page = max(1, (int) $request->query('page', 1));
        $pageSize = min(
            self::MAX_PAGE_SIZE,
            max(1, (int) $request->query('page_size', self::DEFAULT_PAGE_SIZE))
        );
        $sort = (string) $request->query('sort', 'created_at');
        $requestedDirection = strtolower((string) $request->query('direction', 'asc'));
        $direction = $requestedDirection === 'desc' ? 'DESC' : 'ASC';
        $result = $this->service->listRecordsPage($slug, $page, $pageSize, $sort, $direction);
        $totalPages = max(1, (int) ceil($result['total'] / $pageSize));

        Response::make()->json($result['records'], [
            'page' => $page,
            'page_size' => $pageSize,
            'total' => $result['total'],
            'total_pages' => $totalPages,
            'sort' => $result['sort'],
            'direction' => strtolower($direction),
        ]);
    }

    /**
     * Narrow, unpaginated filter used by the reverse relation tab (STORY
     * 10.3 §9) — GET /entities/{slug}/records?field=...&value=....
     */
    private function indexByField(string $slug, string $field, string $value): void
    {
        try {
            $records = $this->service->findRecordsByField($slug, $field, $value);
        } catch (InvalidArgumentException $e) {
            Response::make()->unprocessable($e->getMessage());
            return;
        }

        Response::make()->json($records);
    }

    /**
     * POST /api/v1/entities/{slug}/records
     * Creates a new record. Body is validated against the entity schema.
     */
    public function create(array $params, ?Request $request = null): void
    {
        $request ??= $this->requestFactory()->fromGlobals($params);
        $slug    = (string) ($params['slug'] ?? '');
        $data    = $request->allBody();
        $ownerId = $this->resolveOwnerId($request);

        if ($slug === '') {
            Response::make()->notFound(self::MSG_SLUG_REQUIRED);
            return;
        }

        $hasError = false;
        $record = null;

        try {
            $record = $this->service->createRecord($slug, $data, $ownerId);
        } catch (ValidationException | HookException | EntityServiceException | RepositoryException $e) {
            $this->respondEntityWriteFailure($e);
            $hasError = true;
        }

        if ($hasError) {
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
        $request ??= $this->requestFactory()->fromGlobals($params);
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
        $request ??= $this->requestFactory()->fromGlobals($params);
        $slug    = (string) ($params['slug'] ?? '');
        $id      = (string) ($params['id'] ?? '');
        $data    = $request->allBody();

        if ($id === '') {
            Response::make()->notFound(self::MSG_RECORD_ID_REQUIRED);
            return;
        }

        $hasError = false;
        $record = null;

        try {
            $record = $this->service->updateRecord($id, $slug, $data);
        } catch (ValidationException | HookException | EntityServiceException | RepositoryException $e) {
            $this->respondEntityWriteFailure($e);
            $hasError = true;
        }

        if ($hasError) {
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
        $request ??= $this->requestFactory()->fromGlobals($params);
        $id = (string) ($params['id'] ?? '');

        if ($id === '') {
            Response::make()->notFound(self::MSG_RECORD_ID_REQUIRED);
            return;
        }

        try {
            $this->service->deleteRecord($id);
        } catch (HookException | EntityServiceException | RepositoryException $e) {
            $this->respondEntityWriteFailure($e);
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
        $request ??= $this->requestFactory()->fromGlobals($params);
        $slug = (string) ($params['slug'] ?? '');

        if ($slug === '') {
            Response::make()->notFound(self::MSG_SLUG_REQUIRED);
            return;
        }

        $pluginTabs = $this->hookDispatcher->applyFilter('registerTabs', [], ['entity' => $slug]);
        $relationTabs = $this->reverseRelationTabResolver->resolve($slug);
        $tabs = array_merge($pluginTabs, $relationTabs);

        Response::make()->json(['tabs' => $tabs, 'entity' => $slug]);
    }

    /**
     * GET /api/v1/entities/{slug}/actions
     * Returns the list of row actions registered by plugins for this entity.
     * Calls applyFilter('registerActions') to collect plugin contributions.
     */
    public function actions(array $params, ?Request $request = null): void
    {
        $request ??= $this->requestFactory()->fromGlobals($params);
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

    private function respondEntityWriteFailure(
        ValidationException | HookException | EntityServiceException | RepositoryException $exception
    ): void {
        if ($exception instanceof ValidationException) {
            Response::make()->unprocessable('Validation failed.', $exception->getErrors());
        } elseif ($exception instanceof HookException) {
            Response::make()->unprocessable($exception->getMessage());
        } else {
            Response::make()->notFound($exception->getMessage());
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
