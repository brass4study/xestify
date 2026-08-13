<?php

declare(strict_types=1);

namespace Xestify\plugins\lifecycle;

use PDO;
use Throwable;
use Xestify\plugins\discovery\PluginSchemaCodec;
use Xestify\plugins\discovery\PluginSourceService;
use Xestify\plugins\schema\InstalledPluginSchemaValidator;
use Xestify\plugins\schema\PluginTypeGuard;
use Xestify\repositories\PluginRepository;

final class PluginSyncService
{
    private const RESULT_REGISTERED = 'registered';
    private const RESULT_UNCHANGED = 'unchanged';
    private const RESULT_OUTDATED = 'outdated';
    private const RESULT_ERROR = 'error';

    public function __construct(
        private PDO $pdo,
        private PluginSourceService $pluginSource,
        private PluginRepository $pluginRepository,
        private PluginLifecycleInvoker $lifecycleInvoker,
        private PluginSchemaCodec $schemaCodec,
        private InstalledPluginSchemaValidator $installedSchemaValidator,
        private PluginTypeGuard $typeGuard = new PluginTypeGuard()
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
            $this->pdo->beginTransaction();

            try {
                $this->pluginRepository->insert($manifest, $schema);
                $this->lifecycleInvoker->onInstall($slug);

                $this->pdo->commit();
            } catch (Throwable $e) {
                if ($this->pdo->inTransaction()) {
                    $this->pdo->rollBack();
                }

                throw $e;
            }

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

        $this->typeGuard->assertTypeUnchanged($existing, $manifest);

        $installedVersion = (string) $existing['version'];
        $availableVersion = (string) $manifest['version'];
        $result = version_compare($availableVersion, $installedVersion, '>')
            ? self::RESULT_OUTDATED
            : self::RESULT_UNCHANGED;

        if ($result === self::RESULT_UNCHANGED && in_array($manifest['type'] ?? '', ['entity', 'extension'], true)) {
            $installedSchema = $this->schemaCodec->decode($existing['schema_json'] ?? null);
            if ($installedSchema !== null) {
                $this->installedSchemaValidator->assertContainsCanonical($slug, $installedSchema, $schema);
            }
        }

        $this->refreshMetadataIfNeeded($existing, $manifest);
        $this->backfillSchemaIfMissing($existing, $manifest, $schema);

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
    private function refreshMetadataIfNeeded(array $existing, array $manifest): void
    {
        $name = (string) $manifest['name'];
        if ((string) $existing['name'] !== $name) {
            $this->pluginRepository->refreshName((string) $manifest['slug'], $name);
        }
    }

    /**
     * @param array<string, mixed> $existing
     * @param array<string, mixed> $manifest
     * @param array<string, mixed>|null $schema
     */
    private function backfillSchemaIfMissing(array $existing, array $manifest, ?array $schema): void
    {
        if ($schema === null) {
            return;
        }

        if ($this->schemaCodec->decode($existing['schema_json'] ?? null) !== null) {
            return;
        }

        $this->pluginRepository->fillSchemaIfMissing((string) $manifest['slug'], $schema);
    }
}
