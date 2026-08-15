<?php

declare(strict_types=1);

namespace Xestify\plugins\contracts;

use PDO;
use Xestify\exceptions\HookException;
use Xestify\core\HookDispatcher;

/**
 * Base beforeSave hook that enforces a single field's uniqueness within one
 * entity instance's own records — the pattern both `persons` (email) and
 * `products` (sku) implement identically apart from the field/entity names
 * and the duplicate-error wording.
 *
 * Recognizes "my own" entity by pluginName() (the fixed technical identity,
 * STORY 10.3), not by a hardcoded slug. `slug` remains the unique DB column
 * (`plugins_slug_unique`) and is still what scopes the actual duplicate
 * query (each instance's records are independent: a `clients` instance and
 * a `distributors` instance, both plugin_name='persons', each enforce
 * uniqueness only within their own records) — but `slug` is editable from
 * PluginConfig and is NOT what identifies which code owns an entity, so a
 * hardcoded slug literal would silently stop matching the moment an admin
 * renames it, and would never match a second instance of the same plugin at
 * all. plugin_name is fixed and, unlike slug, may repeat across several
 * rows/instances — matching by it (not slug) is what makes this hook
 * recognize every current and future instance of "its" plugin.
 */
abstract class AbstractUniqueFieldHook
{
    public function __construct(protected PDO $pdo)
    {
    }

    abstract protected function pluginName(): string;

    abstract protected function fieldName(): string;

    /**
     * @param non-empty-string $value
     */
    abstract protected function duplicateMessage(string $value): string;

    /**
     * Register this hook on the given dispatcher.
     */
    public function register(HookDispatcher $dispatcher): void
    {
        $dispatcher->register(
            'beforeSave',
            fn(array $ctx): array => $this->enforceUniqueness($ctx),
            priority: 5
        );
    }

    /**
     * @param  array<string, mixed> $ctx
     * @return array<string, mixed>
     * @throws HookException when a duplicate value is found
     */
    private function enforceUniqueness(array $ctx): array
    {
        $slug = (string) ($ctx['slug'] ?? '');
        if ($slug === '' || ($ctx['plugin_name'] ?? '') !== $this->pluginName()) {
            return $ctx;
        }

        $field = $this->fieldName();
        $value = (string) ($ctx['data'][$field] ?? '');

        if ($value === '') {
            return $ctx;
        }

        $recordId = (string) ($ctx['id'] ?? ($ctx['data']['id'] ?? ''));

        $sql = "SELECT COUNT(*) FROM plugin_entity_data
                WHERE entity_slug = :slug
                  AND content->>'{$field}' = :value
                  AND deleted_at IS NULL"
             . ($recordId !== '' ? ' AND id <> :id' : '');

        $stmt = $this->pdo->prepare($sql);
        $params = [':slug' => $slug, ':value' => $value];

        if ($recordId !== '') {
            $params[':id'] = $recordId;
        }

        $stmt->execute($params);
        $count = (int) $stmt->fetchColumn();

        if ($count > 0) {
            throw new HookException($this->duplicateMessage($value));
        }

        return $ctx;
    }
}
