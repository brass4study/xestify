<?php

declare(strict_types=1);

namespace Xestify\services;

use PDO;
use PDOException;
use Throwable;
use Xestify\controllers\ExtensionPluginDataStore;
use Xestify\exceptions\EntityServiceException;
use Xestify\exceptions\HookException;
use Xestify\exceptions\RepositoryException;
use Xestify\exceptions\ValidationException;
use Xestify\core\HookDispatcher;
use Xestify\plugins\schema\ReverseRelationTabResolver;
use Xestify\repositories\GenericRepository;
use Xestify\validation\schema\SchemaFieldExtractor;

/**
 * EntityService — orchestrates CRUD operations on dynamic entities.
 *
 * Fetches the current schema from plugins.schema_json, validates incoming data
 * with ValidationService, persists records via GenericRepository, and
 * dispatches beforeSave/afterSave hooks via HookDispatcher.
 *
 * Methods:
 *   createRecord(string $entitySlug, array $data, ?string $ownerId): array
 *   updateRecord(string $id, string $entitySlug, array $data): array
 *   deleteRecord(string $id): void
 *   getRecord(string $id): ?array
 *   listRecords(string $entitySlug, bool $includeDeleted): array
 */
final class EntityService
{
    private const SCHEMA_QUERY =
        "SELECT manifest_json->>'name' AS plugin_name, schema_json FROM plugins
         WHERE slug = :slug";

    public function __construct(
        private GenericRepository $repository,
        private ValidationService $validator,
        private PDO $pdo,
        private ExtensionPluginDataStore $extensionDataStore,
        private ReverseRelationTabResolver $reverseRelationTabResolver,
        private ?HookDispatcher $hooks = null,
        private SchemaFieldExtractor $fieldExtractor = new SchemaFieldExtractor(),
        private EntityOptionLabelBuilder $optionLabelBuilder = new EntityOptionLabelBuilder()
    ) {
    }

    /**
     * Create a new record for the given entity type.
     * Full schema validation (all required fields enforced).
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     * @throws ValidationException        when data does not satisfy schema
     * @throws EntityServiceException     when schema is not found
     */
    public function createRecord(string $entitySlug, array $data, ?string $ownerId = null): array
    {
        $plugin = $this->fetchCurrentPluginRow($entitySlug);
        $result = $this->validator->validate($data, $plugin['schema']);

        if (!$result->isValid()) {
            throw new ValidationException($result->errors());
        }

        $this->pdo->beginTransaction();

        try {
            $context = $this->dispatchBefore('beforeSave', $entitySlug, $plugin['plugin_name'], $data);
            $record  = $this->repository->create($entitySlug, $context['data'], $ownerId);
            $this->dispatchAfter('afterSave', $entitySlug, $record);

            $this->pdo->commit();
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $e;
        }

        return $record;
    }

    /**
     * Partially update an existing record.
     * Only the provided fields are validated and merged into the stored content.
     * Required-field validation is skipped for partial updates.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     * @throws ValidationException        when provided fields fail type/bounds checks
     * @throws EntityServiceException     when schema is not found
     */
    public function updateRecord(string $id, string $entitySlug, array $data): array
    {
        $plugin = $this->fetchCurrentPluginRow($entitySlug);
        $result = $this->validator->validate($data, $plugin['schema'], false);

        if (!$result->isValid()) {
            throw new ValidationException($result->errors());
        }

        $this->pdo->beginTransaction();

        try {
            $context = $this->dispatchBefore('beforeSave', $entitySlug, $plugin['plugin_name'], $data, $id);
            $record  = $this->repository->update($id, $context['data']);
            $this->dispatchAfter('afterSave', $entitySlug, $record);

            $this->pdo->commit();
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $e;
        }

        return $record;
    }

    /**
     * Soft-delete a record and cascade-clean everything that points at it:
     * plugin_extension_data rows (hard delete, that table has no deleted_at)
     * and dispatches beforeDelete/afterDelete so plugins can react. Blocks
     * the operation when another entity has records depending on this one
     * via schema.relations[] (STORY 10.3 §8/§9's reverse-relation lookup).
     *
     * @throws \Xestify\exceptions\RepositoryException when not found
     * @throws HookException when a dependent record blocks deletion, or a
     *                       beforeDelete hook blocks it
     * @throws EntityServiceException when the entity's schema is not found
     */
    public function deleteRecord(string $id): void
    {
        $record = $this->repository->find($id);
        if ($record === null) {
            throw new RepositoryException('Record not found or already deleted: ' . $id);
        }

        $entitySlug = (string) $record['entity_slug'];
        $plugin = $this->fetchCurrentPluginRow($entitySlug);

        $this->guardNoDependentRecords($entitySlug, $id);

        $this->pdo->beginTransaction();

        try {
            $this->dispatchBefore('beforeDelete', $entitySlug, $plugin['plugin_name'], [], $id);
            $this->repository->delete($id);
            $this->extensionDataStore->deleteByRecord($entitySlug, $id);
            $this->dispatchAfter('afterDelete', $entitySlug, $record);

            $this->pdo->commit();
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $e;
        }
    }

    /**
     * Blocks deletion when another entity declares a schema.relations[]
     * entry targeting this entity and has at least one record pointing at
     * $recordId. Reuses ReverseRelationTabResolver (STORY 10.3 §9, already
     * scans active entity plugins for relations targeting a given entity).
     * Queries the repository directly (not the public findRecordsByField()
     * used by the record-filter API) since $relation['key'] is already a
     * trusted key taken straight from the source entity's own schema, not
     * unvalidated request input.
     */
    private function guardNoDependentRecords(string $entitySlug, string $recordId): void
    {
        foreach ($this->reverseRelationTabResolver->resolve($entitySlug) as $relation) {
            $dependents = $this->repository->findByFieldValue($relation['source_entity'], $relation['key'], $recordId);
            if ($dependents !== []) {
                throw new HookException(sprintf(
                    "No se puede borrar: %d registro(s) de '%s' dependen de este registro.",
                    count($dependents),
                    $relation['source_entity']
                ));
            }
        }
    }

    /**
     * Retrieve a single active record by UUID, or null if not found.
     *
     * @return array<string, mixed>|null
     */
    public function getRecord(string $id): ?array
    {
        return $this->repository->find($id);
    }

    /**
     * List all records for an entity type.
     *
     * @return array<int, array<string, mixed>>
     */
    public function listRecords(string $entitySlug, bool $includeDeleted = false): array
    {
        return $this->repository->all($entitySlug, $includeDeleted);
    }

    /**
     * @return array{records: array<int, array<string, mixed>>, total: int, sort: string}
     */
    public function listRecordsPage(
        string $entitySlug,
        int $page,
        int $pageSize,
        string $sort,
        string $direction
    ): array {
        $schema = $this->fetchCurrentPluginRow($entitySlug)['schema'];
        $allowedSorts = array_merge(['id', 'created_at', 'updated_at'], $this->sortableSchemaFields($schema));
        $resolvedSort = in_array($sort, $allowedSorts, true) ? $sort : 'created_at';
        $result = $this->repository->paginate(
            $entitySlug,
            $page,
            $pageSize,
            $resolvedSort,
            $direction
        );
        $result['sort'] = $resolvedSort;
        return $result;
    }

    /**
     * Exact-match lookup on a single content key (STORY 10.3 §9 — feeds the
     * reverse relation tab in EntityEdit). $field must be a declared field/
     * custom_field/editable-identity or a relations[] key of this entity's
     * own schema — same scoping rule already applied to `sort` in
     * listRecordsPage(), so this endpoint cannot be used to probe arbitrary
     * content keys.
     *
     * @return array<int, array<string, mixed>>
     * @throws \InvalidArgumentException when $field is not a known key
     */
    public function findRecordsByField(string $entitySlug, string $field, string $value): array
    {
        $schema = $this->fetchCurrentPluginRow($entitySlug)['schema'];
        if (!in_array($field, $this->knownContentKeys($schema), true)) {
            throw new \InvalidArgumentException("Unknown field '{$field}' for entity: {$entitySlug}");
        }

        return $this->repository->findByFieldValue($entitySlug, $field, $value);
    }

    /**
     * Lightweight {id, label} pairs for this entity's records — feeds relation
     * <select> pickers declared on OTHER entities' schema.relations[].target_entity
     * (no pagination: same trade-off already accepted by listRecords()/all()).
     *
     * @return array<int, array{id: string, label: string}>
     * @throws EntityServiceException when the entity slug has no schema
     */
    public function listOptions(string $entitySlug): array
    {
        $schema = $this->fetchCurrentPluginRow($entitySlug)['schema'];
        $records = $this->repository->all($entitySlug);

        $options = [];
        foreach ($records as $row) {
            $id = (string) ($row['id'] ?? '');
            $decoded = json_decode((string) ($row['content'] ?? '{}'), true);
            $content = is_array($decoded) ? $decoded : [];
            $options[] = ['id' => $id, 'label' => $this->optionLabelBuilder->build($content, $schema, $id)];
        }

        return $options;
    }

    /**
     * @param array<string, mixed> $schema
     * @return array<int, string>
     */
    private function knownContentKeys(array $schema): array
    {
        $keys = array_keys($this->fieldExtractor->extract($schema));

        if (isset($schema['relations']) && is_array($schema['relations'])) {
            foreach ($schema['relations'] as $relation) {
                if (is_array($relation) && is_string($relation['key'] ?? null)) {
                    $keys[] = $relation['key'];
                }
            }
        }

        return $keys;
    }

    /**
     * @param array<string, mixed> $schema
     * @return array<int, string>
     */
    private function sortableSchemaFields(array $schema): array
    {
        $fields = [];
        foreach (array_keys($this->fieldExtractor->extract($schema)) as $name) {
            if (preg_match('/^[A-Za-z_]\w*$/', $name) === 1) {
                $fields[] = $name;
            }
        }
        return $fields;
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Fetch the current plugin_name and decoded schema_json for an entity
     * slug in one query. `slug` stays unique and is how this lookup finds
     * exactly one row; `plugin_name` (the fixed technical identity, STORY
     * 10.3) is NOT unique — several rows/slugs can share it (e.g. two
     * `persons` instances, slugs `clients` and `distributors`) — and is
     * threaded into the beforeSave/afterSave hook context so hook
     * implementations can recognize "an entity backed by my code" by plugin
     * identity, which stays stable across renames and across instances,
     * instead of hardcoding one specific slug.
     *
     * @return array{plugin_name: string, schema: array<string, mixed>}
     * @throws EntityServiceException
     */
    private function fetchCurrentPluginRow(string $entitySlug): array
    {
        try {
            $stmt = $this->pdo->prepare(self::SCHEMA_QUERY);
            $stmt->execute([':slug' => $entitySlug]);
            $row = $stmt->fetch();
        } catch (PDOException $e) {
            throw new EntityServiceException(
                'Failed to fetch schema for entity: ' . $entitySlug,
                0,
                $e
            );
        }

        if ($row === false) {
            throw new EntityServiceException('No schema found for entity: ' . $entitySlug);
        }

        $decoded = json_decode((string) $row['schema_json'], true);

        return [
            'plugin_name' => (string) ($row['plugin_name'] ?? ''),
            'schema' => is_array($decoded) ? $decoded : [],
        ];
    }

    /**
     * Dispatch a beforeSave hook through HookDispatcher.
     * Returns the (possibly mutated) context so callers can use $context['data'].
     * Throws if the hook blocks the operation.
     *
     * @param  array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function dispatchBefore(
        string $hook,
        string $entitySlug,
        string $pluginName,
        array $data,
        ?string $recordId = null
    ): array {
        $context = ['slug' => $entitySlug, 'plugin_name' => $pluginName, 'data' => $data];

        if ($recordId !== null) {
            $context['id'] = $recordId;
        }

        if ($this->hooks === null) {
            return $context;
        }

        return $this->hooks->execute($hook, $context);
    }

    /**
     * Dispatch an afterSave hook through HookDispatcher (non-blocking).
     *
     * @param  array<string, mixed> $record
     */
    private function dispatchAfter(string $hook, string $entitySlug, array $record): void
    {
        if ($this->hooks === null) {
            return;
        }

        $this->hooks->execute($hook, ['slug' => $entitySlug, 'record' => $record]);
    }
}
