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
}
