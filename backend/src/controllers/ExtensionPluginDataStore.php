<?php

declare(strict_types=1);

namespace Xestify\controllers;

use PDO;

final class ExtensionPluginDataStore
{
    public function __construct(private PDO $pdo)
    {
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function fetchRows(string $pluginSlug, string $entity, string $recordId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, plugin_slug, entity_slug, record_id, content, created_at
               FROM plugin_extension_data
              WHERE plugin_slug = :plugin
                AND entity_slug = :entity
                AND record_id   = :record_id
              ORDER BY created_at ASC'
        );
        $stmt->execute([
            ':plugin' => $pluginSlug,
            ':entity' => $entity,
            ':record_id' => $recordId,
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>|false
     */
    public function insertRow(string $pluginSlug, string $entity, string $recordId, array $data): array|false
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO plugin_extension_data (plugin_slug, entity_slug, record_id, content)
             VALUES (:plugin, :entity, :record_id, :content)
             RETURNING id, plugin_slug, entity_slug, record_id, content, created_at'
        );
        $stmt->execute([
            ':plugin' => $pluginSlug,
            ':entity' => $entity,
            ':record_id' => $recordId,
            ':content' => $this->encodeContent($data),
        ]);

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>|false
     */
    public function updateRow(string $itemId, string $pluginSlug, string $entity, string $recordId, array $data): array|false
    {
        $stmt = $this->pdo->prepare(
            'UPDATE plugin_extension_data
                SET content = content || :content::jsonb
              WHERE id          = :item_id
                AND plugin_slug = :plugin
                AND entity_slug = :entity
                AND record_id   = :record_id
            RETURNING id, plugin_slug, entity_slug, record_id, content, created_at'
        );
        $stmt->execute([
            ':content' => $this->encodeContent($data),
            ':item_id' => $itemId,
            ':plugin' => $pluginSlug,
            ':entity' => $entity,
            ':record_id' => $recordId,
        ]);

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function deleteRow(string $itemId, string $pluginSlug, string $entity, string $recordId): int
    {
        $stmt = $this->pdo->prepare(
            'DELETE FROM plugin_extension_data
              WHERE id          = :item_id
                AND plugin_slug = :plugin
                AND entity_slug = :entity
                AND record_id   = :record_id'
        );
        $stmt->execute([
            ':item_id' => $itemId,
            ':plugin' => $pluginSlug,
            ':entity' => $entity,
            ':record_id' => $recordId,
        ]);

        return $stmt->rowCount();
    }

    /**
     * @param array<string, mixed> $data
     */
    private function encodeContent(array $data): string
    {
        $content = json_encode($data);

        return $content !== false ? $content : '{}';
    }
}
