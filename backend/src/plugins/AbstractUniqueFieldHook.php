<?php

declare(strict_types=1);

namespace Xestify\plugins;

use PDO;
use Xestify\exceptions\HookException;

/**
 * Base beforeSave hook that enforces a single field's uniqueness across all
 * active records of one entity — the pattern both `clients` (email) and
 * `products` (sku) implement identically apart from the field/entity names
 * and the duplicate-error wording.
 */
abstract class AbstractUniqueFieldHook
{
    public function __construct(protected PDO $pdo)
    {
    }

    abstract protected function entitySlug(): string;

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
        if (($ctx['slug'] ?? '') !== $this->entitySlug()) {
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
        $params = [':slug' => $this->entitySlug(), ':value' => $value];

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
