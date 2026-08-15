<?php

declare(strict_types=1);

namespace Xestify\services;

use DomainException;
use InvalidArgumentException;
use OutOfBoundsException;
use PDO;
use Throwable;
use Xestify\plugins\discovery\PluginSourceService;
use Xestify\plugins\lifecycle\PluginDeletionService;
use Xestify\plugins\lifecycle\PluginIdentityService;
use Xestify\plugins\lifecycle\PluginOutdatedService;
use Xestify\plugins\lifecycle\PluginRollbackService;
use Xestify\plugins\lifecycle\PluginStatusService;
use Xestify\plugins\lifecycle\PluginSyncService;
use Xestify\plugins\lifecycle\PluginUpdateService;
use Xestify\plugins\schema\PluginConfigService;
use Xestify\repositories\PluginRepository;

/**
 * Orchestrates plugin administration actions (sync/update/rollback/activate/
 * register/config). The fields/target_entity shape itself — validation,
 * normalization, persistence — lives in PluginConfigService (STORY 10.3
 * §2bis cleanup, split out to keep this class under the method-count
 * threshold); this class locks rows, opens/closes transactions, and applies
 * identity edits via PluginIdentityService.
 */
class PluginAdministrationService
{
    private const IDENTITY_PAYLOAD_KEYS = ['slug', 'name', 'description'];

    public function __construct(
        private PDO $pdo,
        private PluginRepository $pluginRepository,
        private PluginSyncService $pluginSyncService,
        private PluginOutdatedService $pluginOutdatedService,
        private PluginUpdateService $pluginUpdateService,
        private PluginRollbackService $pluginRollbackService,
        private PluginStatusService $pluginStatusService,
        private PluginConfigService $pluginConfigService,
        private PluginIdentityService $pluginIdentityService,
        private PluginDeletionService $pluginDeletionService,
        private PluginSourceService $pluginSourceService
    ) {
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listInstalled(): array
    {
        return $this->pluginRepository->listInstalled();
    }

    /**
     * Disk plugin_name folders, each with its manifest-declared "label"
     * (suggested value for "Nombre") and "name" (technical identifier,
     * suggested value for "Slug") when registering a new instance (STORY
     * 10.3). plugin_name is not unique: a folder never becomes "used up" by
     * a previous registration, since a new instance (its own row, its own
     * slug) can always be added alongside existing ones — e.g. two rows with
     * plugin_name='persons' and slugs 'clients'/'distributors'. So every
     * discovered folder is always a valid choice for a new manual
     * registration, regardless of how many rows it already has.
     *
     * @return array<int, array{plugin_name: string, label: string, suggested_slug: string, description: string, plugin_type: string, config: array<string, mixed>}>
     */
    public function listAvailableForRegistration(): array
    {
        $available = [];
        foreach ($this->pluginSourceService->discover() as $pluginName) {
            $manifest = $this->pluginSourceService->readManifest($pluginName);
            $available[] = [
                'plugin_name' => $pluginName,
                'label' => (string) ($manifest['label'] ?? $pluginName),
                'suggested_slug' => (string) ($manifest['name'] ?? $pluginName),
                'description' => (string) ($manifest['description'] ?? ''),
                'plugin_type' => (string) ($manifest['type'] ?? ''),
                'config' => $this->pluginConfigService->buildAvailablePluginPreview($manifest),
            ];
        }

        return $available;
    }

    /**
     * Registers a new instance of a plugin_name discovered on disk (STORY
     * 10.3 manual registration) — plugin_name is not unique, so this can add
     * another instance of an already-installed plugin_name too, as long as
     * the resulting slug is free. The manifest's own slug (= plugin_name) is
     * only usable for a first instance; any later instance needs an explicit
     * $overrides['slug'], applied directly at insert time (not afterward via
     * PluginIdentityService::updateIdentity(), which would collide on the
     * manifest's default slug before ever reaching the override). Reuses
     * PluginSyncService's shared install step (its own transaction, same as
     * syncAll()'s automatic registration), then activates the new instance
     * (its own transaction too, via PluginStatusService::activate() so
     * onActivate() fires exactly as it would from the "Activar" button) —
     * manual registration is a deliberate, reviewed action, so the plugin
     * should be usable immediately, unlike syncAll()'s bulk auto-discovery,
     * which still registers 'inactive' pending admin review. Optional
     * identity/config overrides are applied afterward, together in a third
     * transaction — PluginConfigService::applyConfigPayload() does not
     * itself require the row to be active (unlike saveConfig()'s
     * assertConfigurablePlugin()), it just happens to already be active by
     * the time registerNew() reaches this step.
     *
     * @param array<string, mixed> $overrides optional slug/name/description/fields/target_entity
     * @return array<string, mixed>
     */
    public function registerNew(string $pluginName, array $overrides): array
    {
        $availableNames = array_column($this->listAvailableForRegistration(), 'plugin_name');
        if (!in_array($pluginName, $availableNames, true)) {
            throw new InvalidArgumentException("Plugin '{$pluginName}' was not found on disk.");
        }

        $slugOverride = array_key_exists('slug', $overrides) ? trim((string) $overrides['slug']) : '';
        if ($slugOverride !== '') {
            $this->pluginIdentityService->assertValidSlug($slugOverride);
            if ($this->pluginRepository->findBySlug($slugOverride) !== null) {
                throw new InvalidArgumentException("El slug '{$slugOverride}' ya está en uso.");
            }
        }

        $inserted = $this->pluginSyncService->installFromManifest(
            $pluginName,
            $slugOverride !== '' ? $slugOverride : null
        );
        $inserted = $this->pluginStatusService->activate((string) $inserted['slug']);

        $needsIdentityUpdate = $this->payloadHasIdentityFields($overrides);
        $needsConfigUpdate = array_key_exists('fields', $overrides);

        if (!$needsIdentityUpdate && !$needsConfigUpdate) {
            return $inserted;
        }

        $this->pdo->beginTransaction();

        try {
            if ($needsIdentityUpdate) {
                $inserted = $this->pluginIdentityService->updateIdentity($inserted, $overrides);
            }

            if ($needsConfigUpdate) {
                $inserted = $this->pluginConfigService->applyConfigPayload($inserted, $overrides);
            }

            $this->pdo->commit();
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $e;
        }

        return $inserted;
    }

    /**
     * @return array{
     *   summary: array{discovered:int, registered:int, unchanged:int, outdated:int, errors:int},
     *   plugins: array<string, array<string, mixed>>
     * }
     */
    public function syncAll(): array
    {
        return $this->pluginSyncService->syncAll();
    }

    /**
     * @return array<int, array{
     *   slug: string,
     *   name: string,
     *   plugin_type: string,
     *   installed_version: string,
     *   available_version: string
     * }>
     */
    public function getOutdated(): array
    {
        return $this->pluginOutdatedService->getOutdated();
    }

    /**
     * @return array{
     *   plugin: array<string, mixed>,
     *   update: array<string, mixed>
     * }
     */
    public function update(string $slug): array
    {
        return $this->pluginUpdateService->update($slug);
    }

    /**
     * @return array{
     *   plugin: array<string, mixed>,
     *   rollback: array<string, mixed>
     * }
     */
    public function rollback(string $slug): array
    {
        return $this->pluginRollbackService->rollback($slug);
    }

    /**
     * @return array<string, mixed>
     */
    public function activate(string $slug): array
    {
        return $this->pluginStatusService->activate($slug);
    }

    /**
     * @return array<string, mixed>
     */
    public function deactivate(string $slug): array
    {
        return $this->pluginStatusService->deactivate($slug);
    }

    /**
     * @return array{plugin: array<string, mixed>, config: array<string, mixed>}
     */
    public function getConfig(string $slug): array
    {
        $plugin = $this->pluginRepository->findBySlug($slug);
        if ($plugin === null) {
            throw new OutOfBoundsException("Plugin '{$slug}' was not found.");
        }

        $this->assertConfigurablePlugin($plugin);

        return $this->pluginConfigService->buildConfigResponse($plugin);
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{plugin: array<string, mixed>, config: array<string, mixed>}
     */
    public function saveConfig(string $slug, array $payload): array
    {
        $this->pdo->beginTransaction();

        try {
            $plugin = $this->pluginRepository->lockBySlug($slug);
            if ($plugin === null) {
                throw new OutOfBoundsException("Plugin '{$slug}' was not found.");
            }

            $this->assertConfigurablePlugin($plugin);

            if ($this->payloadHasIdentityFields($payload)) {
                $plugin = $this->pluginIdentityService->updateIdentity($plugin, $payload);
            }

            $updated = $this->pluginConfigService->applyConfigPayload($plugin, $payload);

            $this->pdo->commit();

            return $this->pluginConfigService->buildConfigResponse($updated);
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $e;
        }
    }

    /**
     * Deletes a plugin instance and all its associated data (STORY 10.3 §7).
     * Allowed in any status — an active plugin is deactivated (onDeactivate())
     * as part of the same operation, the admin does not need to deactivate
     * manually first.
     */
    public function deletePlugin(string $slug): void
    {
        $this->pdo->beginTransaction();

        try {
            $plugin = $this->pluginRepository->lockBySlug($slug);
            if ($plugin === null) {
                throw new OutOfBoundsException("Plugin '{$slug}' was not found.");
            }

            $this->pluginDeletionService->deletePlugin($plugin);

            $this->pdo->commit();
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $e;
        }
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function payloadHasIdentityFields(array $payload): bool
    {
        foreach (self::IDENTITY_PAYLOAD_KEYS as $key) {
            if (array_key_exists($key, $payload)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $plugin
     */
    private function assertConfigurablePlugin(array $plugin): void
    {
        $pluginType = (string) ($plugin['manifest_json']['type'] ?? '');
        if (!in_array($pluginType, ['entity', 'extension'], true)) {
            throw new DomainException('Only entity or extension plugins can be configured.');
        }

        if (($plugin['status'] ?? null) !== 'active') {
            throw new DomainException('Only active plugins can be configured.');
        }
    }
}
