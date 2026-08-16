<?php

declare(strict_types=1);

namespace Xestify\repositories;

use PDO;
use Xestify\exceptions\PluginException;
use Xestify\plugins\discovery\PluginSchemaCodec;

/**
 * Mutations on the `plugins` table — split from PluginRepository (read-only
 * queries) to keep each class under the method-count threshold. Both classes
 * share the same row shape/decoding convention (manifest_json decoded into a
 * PHP array on every full-row read), duplicated here rather than shared via
 * inheritance/trait since PHP 8.1 doesn't support constants in traits and the
 * two classes have no other coupling.
 */
final class PluginWriteRepository
{
    private const SELECT_COLUMNS = '
        id, slug, status, manifest_json, schema_json, sort_order, installed_at, updated_at
    ';

    /** manifest_json keys an admin can edit after install — update() must
     * preserve these from the current row instead of overwriting them with
     * whatever the disk manifest says (STORY 10.3 §2bis). */
    private const ADMIN_EDITABLE_MANIFEST_KEYS = ['label', 'description', 'target_entity'];

    public function __construct(private PDO $pdo, private PluginSchemaCodec $schemaCodec)
    {
    }

    /**
     * $slug defaults to the manifest's own technical identifier
     * ($manifest['name'], normally the folder name), but a caller can pass a
     * different one — needed to register a second (or later) instance of a
     * plugin_name whose default slug is already taken by an earlier instance
     * (STORY 10.3, plugin_name is not unique).
     *
     * manifest_json is a straight copy of the manifest read from disk (STORY
     * 10.3 §2bis) — it already carries every field this table used to store
     * as separate columns (name/label/label_singular/version/type/
     * core_version/target_entity/description).
     *
     * @param array<string, mixed> $manifest
     * @param array<string, mixed>|null $schema
     * @return array<string, mixed>
     */
    public function insert(string $pluginName, array $manifest, ?array $schema, ?string $slug = null): array
    {
        $slug ??= (string) $manifest['name'];
        $manifestJson = $this->schemaCodec->encode($manifest, $slug);
        $schemaJson = $this->schemaCodec->encode($schema, $slug);

        $stmt = $this->pdo->prepare(
            'INSERT INTO plugins (slug, status, manifest_json, schema_json, sort_order)
             VALUES (:slug, :status, CAST(:manifest_json AS jsonb), CAST(:schema AS jsonb),
                     COALESCE((SELECT MAX(sort_order) FROM plugins), 0) + 1)
             RETURNING ' . self::SELECT_COLUMNS
        );
        $stmt->execute([
            ':slug' => $slug,
            ':status' => 'inactive',
            ':manifest_json' => $manifestJson,
            ':schema' => $schemaJson,
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            throw new PluginException("Failed to insert plugin '{$pluginName}'.");
        }

        return $this->decodeRow($row);
    }

    /**
     * Updates the admin-editable identity fields: slug (own column) plus
     * manifest_json.label/description. plugin_name (manifest_json.name)
     * is never touched here — it stays fixed for the row's lifetime.
     *
     * @return array<string, mixed>
     */
    public function updateIdentity(string $id, string $slug, string $name, string $description): array
    {
        $stmt = $this->pdo->prepare(
            "UPDATE plugins
                SET slug = :slug,
                    manifest_json = jsonb_set(
                        jsonb_set(manifest_json, '{label}', to_jsonb(:name::text)),
                        '{description}', to_jsonb(:description::text)
                    ),
                    updated_at = NOW()
              WHERE id = :id
              RETURNING " . self::SELECT_COLUMNS
        );
        $stmt->execute([
            ':id' => $id,
            ':slug' => $slug,
            ':name' => $name,
            ':description' => $description,
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            throw new PluginException("Failed to update identity for plugin id '{$id}'.");
        }

        return $this->decodeRow($row);
    }

    public function deleteById(string $id): void
    {
        $this->pdo->prepare('DELETE FROM plugins WHERE id = :id')->execute([':id' => $id]);
    }

    /**
     * Retargets extension plugins whose manifest_json.target_entity points
     * at $oldSlug to $newSlug (part of the transactional slug-rename
     * cascade). target_entity lives in manifest_json, not schema_json
     * (STORY 10.3 §2bis).
     */
    public function retargetExtensionSchemas(string $oldSlug, string $newSlug): int
    {
        $stmt = $this->pdo->prepare(
            "UPDATE plugins
                SET manifest_json = jsonb_set(manifest_json, '{target_entity}', to_jsonb(:new_slug::text)),
                    updated_at = NOW()
              WHERE manifest_json->>'type' = 'extension'
                AND manifest_json->>'target_entity' = :old_slug"
        );
        $stmt->execute([':new_slug' => $newSlug, ':old_slug' => $oldSlug]);

        return $stmt->rowCount();
    }

    /**
     * @param array<string, mixed> $schema
     * @return array<string, mixed>|null
     */
    public function fillSchemaIfMissing(string $slug, array $schema): ?array
    {
        $schemaJson = $this->schemaCodec->encode($schema, $slug);

        $stmt = $this->pdo->prepare(
            'UPDATE plugins
                SET schema_json = CAST(:schema_json AS jsonb),
                    updated_at = NOW()
              WHERE slug = :slug
                AND schema_json IS NULL
              RETURNING ' . self::SELECT_COLUMNS
        );
        $stmt->execute([
            ':slug' => $slug,
            ':schema_json' => $schemaJson,
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $this->decodeRow($row);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function updateStatus(string $slug, string $status): ?array
    {
        $stmt = $this->pdo->prepare(
            'UPDATE plugins SET status = :status, updated_at = NOW()
             WHERE slug = :slug
             RETURNING ' . self::SELECT_COLUMNS
        );
        $stmt->execute([
            ':status' => $status,
            ':slug' => $slug,
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $this->decodeRow($row);
    }

    public function updateSortOrder(string $slug, int $sortOrder): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE plugins SET sort_order = :sort_order, updated_at = NOW()
             WHERE slug = :slug'
        );
        $stmt->execute([
            ':sort_order' => $sortOrder,
            ':slug' => $slug,
        ]);
    }

    /**
     * Entity plugins only — extension plugins go through
     * updateExtensionConfig() instead, since they also need to persist
     * target_entity (manifest_json, not schema_json).
     *
     * @param array<string, mixed> $schema
     * @return array<string, mixed>|null
     */
    public function updateSchemaConfig(string $slug, array $schema): ?array
    {
        $schemaJson = $this->schemaCodec->encode($schema, $slug);

        $stmt = $this->pdo->prepare(
            'UPDATE plugins
                SET schema_json = CAST(:schema_json AS jsonb),
                    updated_at = NOW()
              WHERE slug = :slug
              RETURNING ' . self::SELECT_COLUMNS
        );
        $stmt->execute([
            ':slug' => $slug,
            ':schema_json' => $schemaJson,
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $this->decodeRow($row);
    }

    /**
     * Extension plugins' config save: fields live in schema_json,
     * target_entity lives in manifest_json (admin-editable) — both change
     * together in the same UPDATE (STORY 10.3 §2bis).
     *
     * @param array<string, mixed> $schema
     * @return array<string, mixed>|null
     */
    public function updateExtensionConfig(string $slug, array $schema, string $targetEntity): ?array
    {
        $schemaJson = $this->schemaCodec->encode($schema, $slug);

        $stmt = $this->pdo->prepare(
            "UPDATE plugins
                SET schema_json = CAST(:schema_json AS jsonb),
                    manifest_json = jsonb_set(manifest_json, '{target_entity}', to_jsonb(:target_entity::text)),
                    updated_at = NOW()
              WHERE slug = :slug
              RETURNING " . self::SELECT_COLUMNS
        );
        $stmt->execute([
            ':slug' => $slug,
            ':schema_json' => $schemaJson,
            ':target_entity' => $targetEntity,
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $this->decodeRow($row);
    }

    /**
     * @param array<string, mixed> $current row already decoded (lockBySlug/
     *   findBySlug result — manifest_json is a PHP array)
     * @param array<string, mixed> $manifest freshly read from disk
     * @param array<string, mixed>|null $mergedSchema
     * @return array<string, mixed>
     */
    public function persistUpdate(
        array $current,
        array $manifest,
        ?array $mergedSchema,
        bool $schemaChanged
    ): array {
        $manifestJson = $this->schemaCodec->encode(
            $this->mergeManifestForUpdate($current, $manifest),
            (string) $manifest['name']
        );

        if ($schemaChanged) {
            $schemaJson = $this->schemaCodec->encode($mergedSchema, (string) $manifest['name']);

            $stmt = $this->pdo->prepare(
                'UPDATE plugins
                    SET manifest_json = CAST(:manifest_json AS jsonb),
                        schema_json = CAST(:schema_json AS jsonb),
                        updated_at = NOW()
                  WHERE id = :id
                  RETURNING ' . self::SELECT_COLUMNS
            );
            $stmt->execute([
                ':id' => $current['id'],
                ':manifest_json' => $manifestJson,
                ':schema_json' => $schemaJson,
            ]);
        } else {
            $stmt = $this->pdo->prepare(
                'UPDATE plugins
                    SET manifest_json = CAST(:manifest_json AS jsonb),
                        updated_at = NOW()
                  WHERE id = :id
                  RETURNING ' . self::SELECT_COLUMNS
            );
            $stmt->execute([
                ':id' => $current['id'],
                ':manifest_json' => $manifestJson,
            ]);
        }

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            throw new PluginException("Failed to persist updated plugin '{$manifest['name']}'.");
        }

        return $this->decodeRow($row);
    }

    /**
     * Refreshes non-editable fields (name/version/type/core_version/
     * label_singular) from the disk manifest, while preserving whatever the
     * admin set for label/description/target_entity on the current row —
     * update() must never silently overwrite an admin edit.
     *
     * @param array<string, mixed> $current
     * @param array<string, mixed> $manifest
     * @return array<string, mixed>
     */
    private function mergeManifestForUpdate(array $current, array $manifest): array
    {
        $currentManifest = is_array($current['manifest_json'] ?? null) ? $current['manifest_json'] : [];
        $next = $manifest;

        foreach (self::ADMIN_EDITABLE_MANIFEST_KEYS as $key) {
            if (array_key_exists($key, $currentManifest)) {
                $next[$key] = $currentManifest[$key];
            }
        }

        return $next;
    }

    /**
     * @param array<string, mixed> $snapshot row from
     *   PluginUpdateHistoryRepository (manifest_json already decoded)
     * @return array<string, mixed>
     */
    public function restoreFromSnapshot(string $pluginId, array $snapshot): array
    {
        $slug = (string) ($snapshot['slug'] ?? '');
        $manifest = is_array($snapshot['manifest_json'] ?? null) ? $snapshot['manifest_json'] : [];
        $manifestJson = $this->schemaCodec->encode($manifest, $slug);

        $stmt = $this->pdo->prepare(
            'UPDATE plugins
                SET manifest_json = CAST(:manifest_json AS jsonb),
                    status = :status,
                    schema_json = CAST(:schema_json AS jsonb),
                    updated_at = NOW()
              WHERE id = :id
              RETURNING ' . self::SELECT_COLUMNS
        );

        $stmt->execute([
            ':id' => $pluginId,
            ':manifest_json' => $manifestJson,
            ':status' => (string) ($snapshot['status'] ?? ''),
            ':schema_json' => $snapshot['schema_json'] ?? null,
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            throw new PluginException("Failed to restore plugin '{$slug}' from snapshot.");
        }

        return $this->decodeRow($row);
    }

    /**
     * Decodes manifest_json (stored/fetched as a JSON string, like every
     * other JSONB column) into a PHP array so every caller of a full-row
     * method always receives $row['manifest_json'] ready to index into.
     *
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function decodeRow(array $row): array
    {
        $decoded = $this->schemaCodec->decode($row['manifest_json'] ?? null);
        $row['manifest_json'] = is_array($decoded) ? $decoded : [];

        return $row;
    }
}
