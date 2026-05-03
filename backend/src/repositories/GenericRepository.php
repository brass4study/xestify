<?php

declare(strict_types=1);

namespace Xestify\repositories;

use PDO;
use PDOException;
use Xestify\core\Database;
use Xestify\exceptions\RepositoryException;

/**
 * GenericRepository — CRUD operations on the plugin_entity_data table.
 *
 * All queries use prepared statements (PDO). Soft delete is implemented via
 * the deleted_at column; hard deletes are not exposed.
 *
 * Methods:
 *   find(string $id): ?array
 *   all(string $entitySlug, bool $includeDeleted = false): array
 *   create(string $entitySlug, array $content, ?string $ownerId = null): array
 *   update(string $id, array $content): array
 *   delete(string $id): void
 *   restore(string $id): void
 */
class GenericRepository
{
    private const TABLE = 'plugin_entity_data';
    private const SQL_UPDATE = 'UPDATE plugin_entity_data';

    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Retrieve a single record by its UUID (only active / non-deleted rows).
     *
     * @return array<string, mixed>|null  Row data, or null when not found.
     */
    public function find(string $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM ' . self::TABLE .
            ' WHERE id = :id AND deleted_at IS NULL'
        );
        $this->execute($stmt, [':id' => $id]);
        $row = $stmt->fetch();

        return ($row === false) ? null : $row;
    }

    /**
     * Retrieve all active records for a given entity type.
     *
     * @return array<int, array<string, mixed>>
     */
    public function all(string $entitySlug, bool $includeDeleted = false): array
    {
        $sql = 'SELECT * FROM ' . self::TABLE . ' WHERE entity_slug = :slug';
        if (!$includeDeleted) {
            $sql .= ' AND deleted_at IS NULL';
        }
        $sql .= ' ORDER BY created_at ASC';

        $stmt = $this->pdo->prepare($sql);
        $this->execute($stmt, [':slug' => $entitySlug]);

        return $stmt->fetchAll();
    }

    /**
     * Insert a new record. Returns the full row including generated id and
     * timestamps.
     *
     * @param  array<string, mixed>  $content
     * @return array<string, mixed>
     */
    public function create(string $entitySlug, array $content, ?string $ownerId = null): array
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO ' . self::TABLE .
            ' (entity_slug, owner_id, content)
             VALUES (:slug, :owner_id, :content)
             RETURNING *'
        );

        $this->execute($stmt, [
            ':slug'     => $entitySlug,
            ':owner_id' => $ownerId,
            ':content'  => $this->encodeJson($content),
        ]);

        $row = $stmt->fetch();
        if ($row === false) {
            throw new RepositoryException('INSERT did not return a row for entity_slug: ' . $entitySlug);
        }

        return $row;
    }

    /**
     * Merge $content into the existing content JSONB of a record.
     * Only updates the content column; updated_at is refreshed automatically.
     *
     * @param  array<string, mixed>  $content
     * @return array<string, mixed>
     */
    public function update(string $id, array $content): array
    {
        $stmt = $this->pdo->prepare(
            self::SQL_UPDATE .
            ' SET content    = content || :content,
                  updated_at = NOW()
             WHERE id = :id AND deleted_at IS NULL
             RETURNING *'
        );

        $this->execute($stmt, [
            ':id'      => $id,
            ':content' => $this->encodeJson($content),
        ]);

        $row = $stmt->fetch();
        if ($row === false) {
            throw new RepositoryException('Record not found or already deleted: ' . $id);
        }

        return $row;
    }

    /**
     * Soft-delete a record by setting deleted_at = NOW().
     * Throws if the record does not exist or is already deleted.
     */
    public function delete(string $id): void
    {
        $stmt = $this->pdo->prepare(
            self::SQL_UPDATE .
            ' SET deleted_at = NOW()
             WHERE id = :id AND deleted_at IS NULL'
        );

        $this->execute($stmt, [':id' => $id]);

        if ($stmt->rowCount() === 0) {
            throw new RepositoryException('Record not found or already deleted: ' . $id);
        }
    }

    /**
     * Restore a previously soft-deleted record.
     * Throws if the record does not exist or is not deleted.
     */
    public function restore(string $id): void
    {
        $stmt = $this->pdo->prepare(
            self::SQL_UPDATE .
            ' SET deleted_at = NULL,
                  updated_at = NOW()
             WHERE id = :id AND deleted_at IS NOT NULL'
        );

        $this->execute($stmt, [':id' => $id]);

        if ($stmt->rowCount() === 0) {
            throw new RepositoryException('Record not found or not deleted: ' . $id);
        }
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Execute a prepared statement, wrapping PDOException in RepositoryException.
     *
     * @param  array<string, mixed>  $params
     */
    private function execute(\PDOStatement $stmt, array $params): void
    {
        try {
            $stmt->execute($params);
        } catch (PDOException $e) {
            throw new RepositoryException('Query failed: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * JSON-encode an array for use in a JSONB column.
     * Throws RepositoryException when encoding fails.
     *
     * @param  array<string, mixed>  $data
     */
    private function encodeJson(array $data): string
    {
        $json = json_encode($data);
        if ($json === false) {
            throw new RepositoryException('Failed to encode content as JSON');
        }

        return $json;
    }
}
