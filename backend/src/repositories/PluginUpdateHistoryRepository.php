<?php

declare(strict_types=1);

namespace Xestify\repositories;

use PDO;

final class PluginUpdateHistoryRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    /**
     * @param array<string, mixed> $current
     */
    public function insertSnapshot(array $current, string $targetVersion): void
    {
        $this->pdo->prepare(
            'INSERT INTO plugin_update_history (
                slug, name, plugin_type, version, status, schema_version, schema_json, target_version
             ) VALUES (
                :slug, :name, :plugin_type, :version, :status, :schema_version,
                CAST(:schema_json AS jsonb), :target_version
             )'
        )->execute([
            ':slug' => $current['slug'],
            ':name' => $current['name'],
            ':plugin_type' => $current['plugin_type'],
            ':version' => $current['version'],
            ':status' => $current['status'],
            ':schema_version' => $current['schema_version'],
            ':schema_json' => $current['schema_json'],
            ':target_version' => $targetVersion,
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function lockLatestSnapshotForRollback(string $slug, string $currentVersion): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, slug, name, plugin_type, version, status, schema_version, schema_json, target_version, created_at
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

        return $row === false ? null : $row;
    }
}
