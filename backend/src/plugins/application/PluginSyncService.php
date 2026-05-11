<?php

declare(strict_types=1);

namespace Xestify\plugins\application;

use DomainException;
use Throwable;
use Xestify\plugins\infrastructure\PluginSchemaCodec;
use Xestify\plugins\infrastructure\PluginSourceService;
use Xestify\plugins\runtime\PluginLifecycleInvoker;
use Xestify\repositories\PluginRepository;

final class PluginSyncService
{
    private const RESULT_REGISTERED = 'registered';
    private const RESULT_UNCHANGED = 'unchanged';
    private const RESULT_OUTDATED = 'outdated';
    private const RESULT_ERROR = 'error';

    public function __construct(
        private PluginSourceService $pluginSource,
        private PluginRepository $pluginRepository,
        private PluginLifecycleInvoker $lifecycleInvoker,
        private PluginSchemaCodec $schemaCodec,
        private InstalledPluginSchemaValidator $installedSchemaValidator
    ) {
    }

    /**
     * @return array{
     *   summary: array{discovered:int, registered:int, unchanged:int, outdated:int, errors:int},
     *   plugins: array<string, array<string, mixed>>
     * }
     */
    public function syncAll(): array
    {
        $summary = [
            'discovered' => 0,
            'registered' => 0,
            'unchanged' => 0,
            'outdated' => 0,
            'errors' => 0,
        ];
        $results = [];

        $slugs = $this->pluginSource->discover();
        $summary['discovered'] = count($slugs);

        foreach ($slugs as $slug) {
            try {
                $result = $this->syncSlug($slug);
                $results[$slug] = $result;
                $summary[$result['result']]++;
            } catch (Throwable $e) {
                $results[$slug] = [
                    'slug' => $slug,
                    'result' => self::RESULT_ERROR,
                    'message' => $e->getMessage(),
                ];
                $summary['errors']++;
            }
        }

        return ['summary' => $summary, 'plugins' => $results];
    }

    /**
     * @return array<string, mixed>
     */
    private function syncSlug(string $slug): array
    {
        $manifest = $this->pluginSource->readValidatedManifest($slug);
        $schema = $this->pluginSource->readSchema($manifest);
        $existing = $this->pluginRepository->findBySlug($slug);

        if ($existing === null) {
            $this->pluginRepository->insert($manifest, $schema);
            $this->lifecycleInvoker->onInstall($slug);

            return [
                'slug' => $slug,
                'name' => (string) $manifest['name'],
                'plugin_type' => (string) $manifest['type'],
                'result' => self::RESULT_REGISTERED,
                'installed_version' => (string) $manifest['version'],
                'available_version' => (string) $manifest['version'],
                'message' => "Plugin '{$slug}' registered.",
            ];
        }

        $this->ensureInstalledTypeMatchesManifest($existing, $manifest);

        $installedVersion = (string) $existing['version'];
        $availableVersion = (string) $manifest['version'];
        $result = version_compare($availableVersion, $installedVersion, '>')
            ? self::RESULT_OUTDATED
            : self::RESULT_UNCHANGED;

        if ($result === self::RESULT_UNCHANGED && ($manifest['type'] ?? '') === 'entity') {
            $this->installedSchemaValidator->assertContainsCanonical(
                $slug,
                $this->schemaCodec->decode($existing['schema_json'] ?? null),
                $schema
            );
        }

        $this->refreshMetadataIfNeeded($existing, $manifest);

        return [
            'slug' => $slug,
            'name' => (string) $manifest['name'],
            'plugin_type' => (string) $manifest['type'],
            'result' => $result,
            'installed_version' => $installedVersion,
            'available_version' => $availableVersion,
            'message' => $result === self::RESULT_OUTDATED
                ? "Plugin '{$slug}' has an update available."
                : "Plugin '{$slug}' is already synchronized.",
        ];
    }

    /**
     * @param array<string, mixed> $existing
     * @param array<string, mixed> $manifest
     */
    private function ensureInstalledTypeMatchesManifest(array $existing, array $manifest): void
    {
        if ((string) $existing['plugin_type'] !== (string) $manifest['type']) {
            throw new DomainException(
                "Plugin '{$manifest['slug']}' cannot change type from '{$existing['plugin_type']}'"
                . " to '{$manifest['type']}'."
            );
        }
    }

    /**
     * @param array<string, mixed> $existing
     * @param array<string, mixed> $manifest
     */
    private function refreshMetadataIfNeeded(array $existing, array $manifest): void
    {
        $name = (string) $manifest['name'];
        if ((string) $existing['name'] !== $name) {
            $this->pluginRepository->refreshName((string) $manifest['slug'], $name);
        }
    }
}
