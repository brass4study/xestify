<?php

declare(strict_types=1);

namespace Xestify\database\seeders\support;

use PDO;
use PDOStatement;
use Throwable;

/**
 * Shared low-level helpers for BusinessDataSeeder's group seeders (STORY
 * 10.6): per-group idempotency check, transaction-per-group, and prepared
 * INSERTs for plugin_entity_data / plugin_extension_data reused across many
 * rows instead of re-preparing per iteration.
 */
abstract class AbstractGroupSeeder
{
    /** @var list<array{group: string, status: string, count: int}> */
    private array $summary = [];

    public function __construct(protected PDO $pdo, protected string $adminId)
    {
    }

    /**
     * @return list<array{group: string, status: string, count: int}>
     */
    public function summary(): array
    {
        return $this->summary;
    }

    protected function recordSummary(string $group, string $status, int $count): void
    {
        $this->summary[] = ['group' => $group, 'status' => $status, 'count' => $count];
    }

    /**
     * @return list<string> ids de plugin_entity_data ya existentes para ese slug
     */
    protected function fetchExistingEntityIds(string $slug): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id FROM plugin_entity_data
              WHERE entity_slug = :slug AND deleted_at IS NULL
              ORDER BY created_at ASC'
        );
        $stmt->execute([':slug' => $slug]);

        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    protected function countExtensionRows(string $pluginSlug): int
    {
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM plugin_extension_data WHERE plugin_slug = :slug'
        );
        $stmt->execute([':slug' => $pluginSlug]);

        return (int) $stmt->fetchColumn();
    }

    /**
     * @template T
     * @param callable(): T $body
     * @return T
     */
    protected function runInTransaction(callable $body): mixed
    {
        $this->pdo->beginTransaction();

        try {
            $result = $body();
            $this->pdo->commit();

            return $result;
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $e;
        }
    }

    protected function prepareEntityInsert(): PDOStatement
    {
        return $this->pdo->prepare(
            'INSERT INTO plugin_entity_data (entity_slug, owner_id, content)
             VALUES (:entity_slug, :owner_id, :content)
             RETURNING id'
        );
    }

    /**
     * @param array<string, mixed> $content
     */
    protected function insertEntityRow(PDOStatement $stmt, string $slug, array $content): string
    {
        $stmt->execute([
            ':entity_slug' => $slug,
            ':owner_id'    => $this->adminId,
            ':content'     => json_encode($content, JSON_THROW_ON_ERROR),
        ]);

        return (string) $stmt->fetchColumn();
    }

    protected function prepareExtensionInsert(): PDOStatement
    {
        return $this->pdo->prepare(
            'INSERT INTO plugin_extension_data (plugin_slug, entity_slug, record_id, content)
             VALUES (:plugin_slug, :entity_slug, :record_id, :content)'
        );
    }

    /**
     * @param array<string, mixed> $content
     */
    protected function insertExtensionRow(
        PDOStatement $stmt,
        string $pluginSlug,
        string $entitySlug,
        string $recordId,
        array $content
    ): void {
        $stmt->execute([
            ':plugin_slug' => $pluginSlug,
            ':entity_slug' => $entitySlug,
            ':record_id'   => $recordId,
            ':content'     => json_encode($content, JSON_THROW_ON_ERROR),
        ]);
    }
}
