<?php

declare(strict_types=1);

namespace Xestify\plugins\lifecycle;

use InvalidArgumentException;
use Xestify\controllers\ExtensionPluginDataStore;
use Xestify\repositories\GenericRepository;
use Xestify\repositories\PluginRepository;
use Xestify\repositories\PluginUpdateHistoryRepository;
use Xestify\repositories\PluginWriteRepository;

/**
 * Renames a plugin's slug/name/description (STORY 10.3). Assumes it runs
 * inside a transaction already opened by its caller (e.g.
 * PluginAdministrationService::saveConfig()) — this service does not
 * begin/commit/roll back its own transaction.
 */
final class PluginIdentityService
{
    private const SLUG_PATTERN = '/^[a-z][a-z0-9_]*$/';

    public function __construct(
        private PluginRepository $pluginRepository,
        private PluginWriteRepository $pluginWriteRepository,
        private GenericRepository $genericRepository,
        private ExtensionPluginDataStore $extensionPluginDataStore,
        private PluginUpdateHistoryRepository $historyRepository
    ) {
    }

    /**
     * @param array<string, mixed> $current row already locked by the caller
     *   (PluginRepository::lockBySlug)
     * @param array<string, mixed> $payload raw request payload; only the
     *   keys present are applied (partial update)
     * @return array<string, mixed> the updated row
     */
    public function updateIdentity(array $current, array $payload): array
    {
        $nextSlug = array_key_exists('slug', $payload)
            ? trim((string) $payload['slug'])
            : (string) $current['slug'];
        $nextName = array_key_exists('name', $payload)
            ? trim((string) $payload['name'])
            : (string) ($current['manifest_json']['label'] ?? '');
        $nextDescription = array_key_exists('description', $payload)
            ? trim((string) $payload['description'])
            : (string) ($current['manifest_json']['description'] ?? '');

        $this->assertValidSlug($nextSlug);

        $oldSlug = (string) $current['slug'];
        $slugChanged = $nextSlug !== $oldSlug;

        if ($slugChanged) {
            $this->assertSlugAvailable($nextSlug, (string) $current['id']);
        }

        $updated = $this->pluginWriteRepository->updateIdentity(
            (string) $current['id'],
            $nextSlug,
            $nextName,
            $nextDescription
        );

        if ($slugChanged) {
            $this->cascadeSlugRename($oldSlug, $nextSlug, (string) ($current['manifest_json']['type'] ?? ''));
        }

        return $updated;
    }

    /**
     * Exposed publicly so callers that need to validate a slug before it
     * reaches updateIdentity() (e.g. PluginAdministrationService::registerNew(),
     * which must pick the slug for a brand new row before any identity
     * update runs) can reuse the same format rule instead of duplicating it.
     */
    public function assertValidSlug(string $slug): void
    {
        if ($slug === '') {
            throw new InvalidArgumentException('El slug no puede quedar vacío.');
        }

        if (preg_match(self::SLUG_PATTERN, $slug) !== 1) {
            throw new InvalidArgumentException(
                'El slug solo puede contener minúsculas, dígitos y guiones bajos, y debe empezar por una letra.'
            );
        }
    }

    private function assertSlugAvailable(string $slug, string $currentId): void
    {
        $existing = $this->pluginRepository->findBySlug($slug);
        if ($existing !== null && (string) $existing['id'] !== $currentId) {
            throw new InvalidArgumentException("El slug '{$slug}' ya está en uso.");
        }
    }

    private function cascadeSlugRename(string $oldSlug, string $newSlug, string $pluginType): void
    {
        if ($pluginType === 'entity') {
            $this->genericRepository->renameEntitySlug($oldSlug, $newSlug);
            $this->extensionPluginDataStore->renameEntitySlug($oldSlug, $newSlug);
            $this->pluginWriteRepository->retargetExtensionSchemas($oldSlug, $newSlug);
        }

        if ($pluginType === 'extension') {
            $this->extensionPluginDataStore->renamePluginSlug($oldSlug, $newSlug);
        }

        $this->historyRepository->renameSlug($oldSlug, $newSlug);
    }
}
