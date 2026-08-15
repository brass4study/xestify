<?php

declare(strict_types=1);

namespace Xestify\repositories;

use PDO;
use Xestify\plugins\discovery\PluginSchemaCodec;

final class PluginUpdateHistoryRepository
{
    public function __construct(private PDO $pdo, private PluginSchemaCodec $schemaCodec)
    {
    }

    /**
     * @param array<string, mixed> $current row already decoded (manifest_json is a PHP array)
     */
    public function insertSnapshot(array $current, string $targetVersion): void
    {
        $manifestJson = $this->schemaCodec->encode(
            is_array($current['manifest_json'] ?? null) ? $current['manifest_json'] : [],
            (string) ($current['slug'] ?? '')
        );

        $this->pdo->prepare(
            'INSERT INTO plugin_update_history (
                slug, status, manifest_json, schema_json, target_version
             ) VALUES (
                :slug, :status, CAST(:manifest_json AS jsonb), CAST(:schema_json AS jsonb), :target_version
             )'
        )->execute([
            ':slug' => $current['slug'],
            ':status' => $current['status'],
            ':manifest_json' => $manifestJson,
            ':schema_json' => $current['schema_json'],
            ':target_version' => $targetVersion,
        ]);
    }

    /**
     * @return array<string, mixed>|null manifest_json decoded into a PHP array, like every full-row read elsewhere
     */
    public function lockLatestSnapshotForRollback(string $slug, string $currentVersion): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, slug, status, manifest_json, schema_json, target_version, created_at
             FROM plugin_update_history
             WHERE slug = :slug
               AND target_version = :target_version
             ORDER BY created_at DESC
             LIMIT 1
             FOR UPDATE'
        );
        $stmt->execute([
            ':slug' => $slug,
            ':target_version' => $currentVersion,
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            return null;
        }

        $decoded = $this->schemaCodec->decode($row['manifest_json'] ?? null);
        $row['manifest_json'] = is_array($decoded) ? $decoded : [];

        return $row;
    }

    /**
     * Renames slug across historical snapshots (STORY 10.3 slug rename
     * cascade) — without this, rollback stops finding snapshots for a
     * renamed plugin. Part of PluginIdentityService's transaction — no
     * transaction management here.
     */
    public function renameSlug(string $oldSlug, string $newSlug): int
    {
        $stmt = $this->pdo->prepare(
            'UPDATE plugin_update_history SET slug = :new_slug WHERE slug = :old_slug'
        );
        $stmt->execute([':new_slug' => $newSlug, ':old_slug' => $oldSlug]);

        return $stmt->rowCount();
    }

    /**
     * Physically deletes every snapshot for a slug — used when the plugin
     * itself is deleted (STORY 10.3 §7). Part of the caller's transaction —
     * no transaction management here.
     */
    public function deleteBySlug(string $slug): int
    {
        $stmt = $this->pdo->prepare(
            'DELETE FROM plugin_update_history WHERE slug = :slug'
        );
        $stmt->execute([':slug' => $slug]);

        return $stmt->rowCount();
    }
}
