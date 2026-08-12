<?php

declare(strict_types=1);

namespace Xestify\plugins\application;

use DomainException;
use OutOfBoundsException;
use PDO;
use Throwable;
use Xestify\plugins\infrastructure\PluginSchemaCodec;
use Xestify\plugins\infrastructure\PluginSourceService;
use Xestify\plugins\runtime\PluginLifecycleInvoker;
use Xestify\repositories\PluginRepository;
use Xestify\repositories\PluginUpdateHistoryRepository;

final class PluginUpdateService
{
    public function __construct(
        private PDO $pdo,
        private PluginSourceService $pluginSource,
        private PluginRepository $pluginRepository,
        private PluginUpdateHistoryRepository $historyRepository,
        private PluginSchemaCodec $schemaCodec,
        private PluginSchemaMergeService $schemaMergeService,
        private PluginLifecycleInvoker $lifecycleInvoker,
        private InstalledPluginSchemaValidator $installedSchemaValidator,
        private PluginTypeGuard $typeGuard = new PluginTypeGuard()
    ) {
    }

    /**
     * @return array{
     *   plugin: array<string, mixed>,
     *   update: array{
     *     from_version: string,
     *     to_version: string,
     *     schema_changed: bool,
     *     diff: array<string, array{added: string[]}>
     *   }
     * }
     */
    public function update(string $slug): array
    {
        $this->pdo->beginTransaction();

        try {
            $current = $this->pluginRepository->lockBySlug($slug);
            if ($current === null) {
                throw new OutOfBoundsException("Plugin '{$slug}' is not installed.");
            }

            $manifest = $this->pluginSource->readValidatedManifest($slug);
            $this->typeGuard->assertTypeUnchanged($current, $manifest);

            $fromVersion = (string) $current['version'];
            $toVersion = (string) $manifest['version'];
            if (version_compare($toVersion, $fromVersion, '<=')) {
                throw new DomainException("Plugin '{$slug}' has no newer version available on disk.");
            }

            $currentSchema = $this->schemaCodec->decode($current['schema_json'] ?? null);
            $targetSchema = $this->pluginSource->readSchema($manifest);
            $mergedSchema = $currentSchema;
            $schemaChanged = false;
            $diff = $this->schemaMergeService->emptyDiff();

            if (in_array($manifest['type'] ?? '', ['entity', 'extension'], true)) {
                $this->installedSchemaValidator->assertCanApplyUpdate($slug, $currentSchema, $targetSchema);

                $merge = $this->schemaMergeService->mergeAdditively($slug, $currentSchema, $targetSchema);
                $mergedSchema = $merge['schema'];
                $schemaChanged = $merge['changed'];
                $diff = $merge['diff'];
            }

            $this->historyRepository->insertSnapshot($current, $toVersion);

            $context = [
                'slug' => $slug,
                'from_version' => $fromVersion,
                'to_version' => $toVersion,
                'current_plugin' => $current,
                'manifest' => $manifest,
                'current_schema' => $currentSchema,
                'target_schema' => $targetSchema,
                'merged_schema' => $mergedSchema,
            ];
            $this->lifecycleInvoker->onUpdate($slug, $context);

            $plugin = $this->pluginRepository->persistUpdate($current, $manifest, $mergedSchema, $schemaChanged);

            if ((string) ($current['status'] ?? '') === 'active') {
                $this->lifecycleInvoker->onActivate($slug);
            }

            $this->pdo->commit();

            return [
                'plugin' => $plugin,
                'update' => [
                    'from_version' => $fromVersion,
                    'to_version' => $toVersion,
                    'schema_changed' => $schemaChanged,
                    'diff' => $diff,
                ],
            ];
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $e;
        }
    }
}
