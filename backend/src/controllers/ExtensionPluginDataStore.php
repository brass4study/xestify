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
     * @return array<string, mixed>|false
     */
    public function findRow(string $itemId, string $pluginSlug, string $entity, string $recordId): array|false
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, plugin_slug, entity_slug, record_id, content, created_at
               FROM plugin_extension_data
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

        return $stmt->fetch(PDO::FETCH_ASSOC);
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
     * Renames entity_slug across extension data attached to that entity's
     * records (STORY 10.3 slug rename cascade) — applies when the ENTITY
     * being renamed is targeted by other plugins' extension data. Part of
     * PluginIdentityService's transaction — no transaction management here.
     */
    public function renameEntitySlug(string $oldSlug, string $newSlug): int
    {
        $stmt = $this->pdo->prepare(
            'UPDATE plugin_extension_data SET entity_slug = :new_slug WHERE entity_slug = :old_slug'
        );
        $stmt->execute([':new_slug' => $newSlug, ':old_slug' => $oldSlug]);

        return $stmt->rowCount();
    }

    /**
     * Renames plugin_slug across an extension plugin's own data (STORY 10.3
     * slug rename cascade) — applies when the EXTENSION plugin itself is
     * renamed. Part of PluginIdentityService's transaction — no transaction
     * management here.
     */
    public function renamePluginSlug(string $oldSlug, string $newSlug): int
    {
        $stmt = $this->pdo->prepare(
            'UPDATE plugin_extension_data SET plugin_slug = :new_slug WHERE plugin_slug = :old_slug'
        );
        $stmt->execute([':new_slug' => $newSlug, ':old_slug' => $oldSlug]);

        return $stmt->rowCount();
    }

    /**
     * Physically deletes every extension-data row attached to an entity's
     * records — used when the ENTITY plugin itself is deleted (STORY 10.3
     * §7), so other plugins' extension data pointing at it doesn't become
     * orphaned. Part of the caller's transaction — no transaction management
     * here.
     */
    public function deleteByEntitySlug(string $entitySlug): int
    {
        $stmt = $this->pdo->prepare(
            'DELETE FROM plugin_extension_data WHERE entity_slug = :slug'
        );
        $stmt->execute([':slug' => $entitySlug]);

        return $stmt->rowCount();
    }

    /**
     * Physically deletes an EXTENSION plugin's own data — used when that
     * extension plugin itself is deleted (STORY 10.3 §7). Part of the
     * caller's transaction — no transaction management here.
     */
    public function deleteByPluginSlug(string $pluginSlug): int
    {
        $stmt = $this->pdo->prepare(
            'DELETE FROM plugin_extension_data WHERE plugin_slug = :slug'
        );
        $stmt->execute([':slug' => $pluginSlug]);

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
