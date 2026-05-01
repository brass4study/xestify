<?php

declare(strict_types=1);

namespace Xestify\services;

use PDO;
use PDOException;
use Xestify\exceptions\EntityServiceException;
use Xestify\exceptions\ValidationException;
use Xestify\repositories\GenericRepository;

/**
 * EntityService — orchestrates CRUD operations on dynamic entities.
 *
 * Fetches the current schema from entity_metadata, validates incoming data
 * with ValidationService, persists records via GenericRepository, and
 * dispatches hooks (stub — implemented in EPIC 4).
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
        'SELECT schema_json FROM entity_metadata
         WHERE entity_slug = :slug
         ORDER BY schema_version DESC
         LIMIT 1';

    public function __construct(
        private GenericRepository $repository,
        private ValidationService $validator,
        private PDO $pdo
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
        $schema = $this->fetchCurrentSchema($entitySlug);
        $errors = $this->validator->validate($data, $schema);

        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        $record = $this->repository->create($entitySlug, $data, $ownerId);
        $this->fireHooks($entitySlug, 'after_create', $record);

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
        $schema = $this->fetchCurrentSchema($entitySlug);
        $errors = $this->validator->validate($data, $schema, false);

        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        $record = $this->repository->update($id, $data);
        $this->fireHooks($entitySlug, 'after_update', $record);

        return $record;
    }

    /**
     * Soft-delete a record.
     *
     * @throws \Xestify\exceptions\RepositoryException when not found
     */
    public function deleteRecord(string $id): void
    {
        $this->repository->delete($id);
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

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Fetch and decode the latest schema_json for an entity slug.
     *
     * @return array<string, mixed>
     * @throws EntityServiceException
     */
    private function fetchCurrentSchema(string $entitySlug): array
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

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Fire registered hooks for an entity event.
     * Intentionally empty stub — hook dispatcher implemented in EPIC 4.
     *
     * @param array<string, mixed> $record // NOSONAR
     */
    private function fireHooks(string $entitySlug, string $hookName, array $record): void // NOSONAR
    {
        // Hook dispatcher stub — all parameters used in EPIC 4 implementation. // NOSONAR
    }
}
