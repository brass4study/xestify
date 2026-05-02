<?php

declare(strict_types=1);

namespace Xestify\models;

use PDO;
use Xestify\exceptions\EntityServiceException;

/**
 * SystemEntity — read model for the entity plugin catalog.
 *
 * Provides cache-backed access to registered entity types stored in the
 * plugins table (plugin_type = 'entity').
 *
 * Methods:
 *   getActive(): array          — all active entities (cached)
 *   getBySlug(string): ?array   — single entity by slug (cached)
 *   findOrFail(string): array   — same but throws on miss
 */
final class SystemEntity
{
    private const QUERY_ALL_ACTIVE =
        'SELECT slug, name, plugin_type, status,
                installed_at AS created_at, updated_at
         FROM plugins
         WHERE plugin_type = \'entity\' AND status = \'active\'
         ORDER BY slug ASC';

    private const QUERY_BY_SLUG =
        'SELECT slug, name, plugin_type, status,
                installed_at AS created_at, updated_at
         FROM plugins
         WHERE slug = :slug AND plugin_type = \'entity\'
         LIMIT 1';

    /** @var array<string, array<string, mixed>>|null  keyed by slug */
    private ?array $cache = null;

    public function __construct(private PDO $pdo)
    {
    }

    /**
     * Return all active entities, loading from DB on first call.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getActive(): array
    {
        $this->loadCache();

        return array_values($this->cache);
    }

    /**
     * Return the entity with the given slug, or null if not found.
     * Prefers the in-memory cache; falls back to a targeted query.
     *
     * @return array<string, mixed>|null
     */
    public function getBySlug(string $slug): ?array
    {
        $this->loadCache();

        if (array_key_exists($slug, $this->cache)) {
            return $this->cache[$slug];
        }

        $stmt = $this->pdo->prepare(self::QUERY_BY_SLUG);
        $stmt->execute([':slug' => $slug]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row === false) {
            return null;
        }

        $this->cache[$slug] = $row;

        return $row;
    }

    /**
     * Return the entity with the given slug, throwing when not found.
     *
     * @return array<string, mixed>
     * @throws EntityServiceException  when the slug is not registered
     */
    public function findOrFail(string $slug): array
    {
        $entity = $this->getBySlug($slug);

        if ($entity === null) {
            throw new EntityServiceException(
                "Entity type '{$slug}' is not a registered active entity plugin."
            );
        }

        return $entity;
    }

    /**
     * Populate the slug-keyed cache from the DB (only once per instance).
     */
    private function loadCache(): void
    {
        if ($this->cache !== null) {
            return;
        }

        $stmt = $this->pdo->query(self::QUERY_ALL_ACTIVE);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $this->cache = [];

        foreach ($rows as $row) {
            $this->cache[(string) $row['slug']] = $row;
        }
    }
}

