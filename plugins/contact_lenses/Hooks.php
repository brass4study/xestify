<?php

declare(strict_types=1);

namespace Xestify\plugins\contact_lenses;

use PDO;
use Xestify\core\HookDispatcher;

/**
 * Hooks for the contact_lenses plugin ("Lentillas" in the UI — the plugin
 * name/directory use an underscore, not the hyphen from the original STORY
 * wording: plugin `name` doubles as a PHP namespace segment
 * (PluginClassLoader::instantiateHooks()) and must also pass
 * PluginIdentityService::SLUG_PATTERN, both of which reject hyphens).
 *
 * Same pattern as plugins/optometries/Hooks.php (STORY 10.5): registers a
 * registerTabs filter hook that injects a "Lentillas" tab for every ACTIVE
 * instance of this plugin (plugin_name='contact_lenses') whose own
 * target_entity configuration allows the entity currently being viewed,
 * embedding each instance's own schema.relations[] (each relation carries
 * its `layer`, so plugin.js can place OD/OS marca/fabricante/distribuidor
 * selects in the right eye column) and schema.fields[] whose origin is
 * 'additional' (fields added later via PluginConfig's "Añadir campo" — no
 * hand-written UI exists for those, so plugin.js renders them generically).
 */
final class Hooks
{
    /** Used when there is no PDO (never happens at real runtime — see
     * PluginClassLoader::instantiateWithOptionalPdo()) or no active instance
     * yet to read a sort_order from. */
    private const DEFAULT_PRIORITY = 50;

    public function __construct(private ?PDO $pdo = null)
    {
    }

    /**
     * Register all hooks for this plugin on the given dispatcher.
     */
    public function register(HookDispatcher $dispatcher): void
    {
        $dispatcher->register(
            'registerTabs',
            function (array $tabs, array $args): array {
                $entity = trim((string) ($args['entity'] ?? ''));
                $tabList = $tabs;

                if ($entity === '') {
                    return $tabList;
                }

                foreach ($this->allowedInstances($entity) as $instance) {
                    $tabList[] = [
                        'id'          => $instance['slug'],
                        'label'       => 'Lentillas',
                        'icon'        => 'fa-glasses',
                        'endpoint'    => '/plugins/' . $instance['slug'] . '/' . $entity . '/{id}',
                        'plugin_name' => 'contact_lenses',
                        'entity'      => $entity,
                        'relations'   => $instance['relations'],
                        'fields'      => $instance['fields'],
                    ];
                }

                return $tabList;
            },
            priority: $this->resolvePriority()
        );
    }

    /**
     * Anchors this plugin's tab priority relative to OTHER extension plugins'
     * hooks: the lowest plugins.sort_order among this plugin's own active
     * instances, so the "Subir"/"Bajar" actions in PluginManager actually
     * move where its tab lands among other plugins' tabs. Falls back to
     * DEFAULT_PRIORITY when there is no PDO or no active instance (nothing
     * would register a tab anyway in that case).
     */
    private function resolvePriority(): int
    {
        if ($this->pdo === null) {
            return self::DEFAULT_PRIORITY;
        }

        $stmt = $this->pdo->prepare(
            "SELECT MIN(sort_order) AS min_sort_order
               FROM plugins
              WHERE manifest_json->>'name' = 'contact_lenses'
                AND manifest_json->>'type' = 'extension'
                AND status = 'active'"
        );
        $stmt->execute();
        $value = $stmt->fetchColumn();

        return $value !== false && $value !== null ? (int) $value : self::DEFAULT_PRIORITY;
    }

    /**
     * @return list<array{slug: string, relations: list<array<string, mixed>>, fields: list<array<string, mixed>>}>
     *   active instances whose target_entity configuration allows $entity,
     *   ordered by their own sort_order (Subir/Bajar), each carrying its own
     *   schema.relations[]/schema.fields[] (see class docblock for why).
     */
    private function allowedInstances(string $entity): array
    {
        if ($this->pdo === null) {
            return [];
        }

        $stmt = $this->pdo->prepare(
            "SELECT slug, manifest_json, schema_json
               FROM plugins
              WHERE manifest_json->>'name' = 'contact_lenses'
                AND manifest_json->>'type' = 'extension'
                AND status = 'active'
              ORDER BY sort_order ASC, slug ASC"
        );
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $allowed = [];
        foreach ($rows as $row) {
            $slug = (string) ($row['slug'] ?? '');
            if ($slug === '') {
                continue;
            }

            $decodedManifest = json_decode((string) ($row['manifest_json'] ?? ''), true);
            $target = is_array($decodedManifest) ? trim((string) ($decodedManifest['target_entity'] ?? '*')) : '*';

            if ($target === '' || $target === '*' || $target === $entity) {
                $allowed[] = [
                    'slug' => $slug,
                    'relations' => $this->extractRelations($row['schema_json'] ?? null),
                    'fields' => $this->extractFields($row['schema_json'] ?? null),
                ];
            }
        }

        return $allowed;
    }

    /**
     * @return array<string|int, mixed> the named section of the decoded
     *   schema_json, or [] when the JSON/section is missing or malformed
     */
    private function decodeSchemaSection(mixed $schemaJson, string $section): array
    {
        $decoded = is_string($schemaJson) ? json_decode($schemaJson, true) : null;

        return is_array($decoded) && is_array($decoded[$section] ?? null) ? $decoded[$section] : [];
    }

    private function stringOr(mixed $value, string $fallback): string
    {
        return is_string($value) ? $value : $fallback;
    }

    private function floatOrNull(mixed $value): ?float
    {
        return is_numeric($value) ? (float) $value : null;
    }

    /**
     * @return list<array{key: string, label: string, target_entity: string, required: bool, layer: string}>
     */
    private function extractRelations(mixed $schemaJson): array
    {
        $result = [];
        foreach ($this->decodeSchemaSection($schemaJson, 'relations') as $relation) {
            if (!is_array($relation) || !is_string($relation['key'] ?? null) || !is_string($relation['target_entity'] ?? null)) {
                continue;
            }

            $result[] = [
                'key' => $relation['key'],
                'label' => $this->stringOr($relation['label'] ?? null, $relation['key']),
                'target_entity' => $relation['target_entity'],
                'required' => ($relation['required'] ?? false) === true,
                'layer' => $this->stringOr($relation['layer'] ?? null, 'general'),
            ];
        }

        return $result;
    }

    /**
     * Only fields with origin:'additional' — the 26 fields the plugin ships
     * with in its own schema.json never declare `origin` at all (raw, as
     * authored on disk), so they never match here; only fields created
     * afterwards via PluginConfig's "Añadir campo"
     * (ExtensionPluginConfigService::mergeFieldDefinition() stamps
     * origin:'additional' on those) do. buildDetailForm() in plugin.js has
     * hand-written UI for every original field already — this is only for
     * the ones it doesn't know about yet.
     *
     * @return list<array{key: string, type: string, label: string, required: bool, layer: string, options: array<int, mixed>|null, min: float|null, max: float|null}>
     */
    private function extractFields(mixed $schemaJson): array
    {
        $result = [];
        foreach ($this->decodeSchemaSection($schemaJson, 'fields') as $key => $definition) {
            if (!is_string($key) || !is_array($definition)) {
                continue;
            }
            if ($this->stringOr($definition['origin'] ?? null, 'base') !== 'additional') {
                continue;
            }

            $result[] = [
                'key' => $key,
                'type' => $this->stringOr($definition['type'] ?? null, 'string'),
                'label' => $this->stringOr($definition['label'] ?? null, $key),
                'required' => ($definition['required'] ?? false) === true,
                'layer' => $this->stringOr($definition['layer'] ?? null, 'general'),
                'options' => is_array($definition['options'] ?? null) ? $definition['options'] : null,
                'min' => $this->floatOrNull($definition['min'] ?? null),
                'max' => $this->floatOrNull($definition['max'] ?? null),
            ];
        }

        return $result;
    }
}
