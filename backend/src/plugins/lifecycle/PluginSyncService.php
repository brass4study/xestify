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
use Xestify\repositories\PluginWriteRepository;

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
        private PluginWriteRepository $pluginWriteRepository,
        private PluginLifecycleInvoker $lifecycleInvoker,
        private PluginSchemaCodec $schemaCodec,
        private InstalledPluginSchemaValidator $installedSchemaValidator,
        private PluginTypeGuard $typeGuard = new PluginTypeGuard()
    ) {
    }

    /**
     * plugin_name is not unique (STORY 10.3): a disk folder can back several
     * independent instances, each with its own slug. syncAll() processes
     * every existing instance of a discovered folder independently — a
     * plugin with two outdated instances reports two "outdated" entries.
     *
     * @return array{
     *   summary: array{discovered:int, registered:int, unchanged:int, outdated:int, errors:int},
     *   plugins: array<string, array<int, array<string, mixed>>>
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

        $pluginNames = $this->pluginSource->discover();
        $summary['discovered'] = count($pluginNames);

        foreach ($pluginNames as $pluginName) {
            try {
                $instanceResults = $this->syncPlugin($pluginName);
                $results[$pluginName] = $instanceResults;
                foreach ($instanceResults as $instanceResult) {
                    $summary[$this->summaryKey((string) $instanceResult['result'])]++;
                }
            } catch (Throwable $e) {
                $results[$pluginName] = [[
                    'slug' => $pluginName,
                    'result' => self::RESULT_ERROR,
                    'message' => $e->getMessage(),
                ]];
                $summary['errors']++;
            }
        }

        return ['summary' => $summary, 'plugins' => $results];
    }

    /**
     * Maps a per-instance result value to its summary counter key — every
     * result value doubles as its own key (registered/unchanged/outdated)
     * except RESULT_ERROR ('error'), whose counter is pluralized ('errors').
     */
    private function summaryKey(string $result): string
    {
        return $result === self::RESULT_ERROR ? 'errors' : $result;
    }

    /**
     * Registers a brand new plugin instance (discovered on disk) as its own
     * transaction. Shared by syncAll() (bulk, automatic — only ever creates
     * the first instance of a plugin_name, $slugOverride always null there)
     * and PluginAdministrationService::registerNew() (manual admin action,
     * also used to create additional instances of an already-installed
     * plugin_name, which requires $slugOverride since the manifest's own
     * slug is already taken by the earlier instance).
     *
     * @return array<string, mixed>
     */
    public function installFromManifest(string $pluginName, ?string $slugOverride = null): array
    {
        $manifest = $this->pluginSource->readValidatedManifest($pluginName);
        $schema = $this->pluginSource->readSchema($manifest);

        $this->pdo->beginTransaction();

        try {
            $inserted = $this->pluginWriteRepository->insert($pluginName, $manifest, $schema, $slugOverride);
            $this->lifecycleInvoker->onInstall($pluginName);

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
     * @return array<int, array<string, mixed>>
     */
    private function syncPlugin(string $pluginName): array
    {
        $existingInstances = $this->pluginRepository->findAllByPluginName($pluginName);

        if ($existingInstances === []) {
            $inserted = $this->installFromManifest($pluginName);

            $insertedVersion = (string) ($inserted['manifest_json']['version'] ?? '');

            return [[
                'slug' => (string) $inserted['slug'],
                'name' => (string) ($inserted['manifest_json']['label'] ?? ''),
                'plugin_type' => (string) ($inserted['manifest_json']['type'] ?? ''),
                'result' => self::RESULT_REGISTERED,
                'installed_version' => $insertedVersion,
                'available_version' => $insertedVersion,
                'message' => "Plugin '{$pluginName}' registered.",
            ]];
        }

        $manifest = $this->pluginSource->readValidatedManifest($pluginName);
        $schema = $this->pluginSource->readSchema($manifest);

        $results = [];
        foreach ($existingInstances as $existing) {
            $results[] = $this->syncExistingInstance($existing, $manifest, $schema);
        }

        return $results;
    }

    /**
     * @param array<string, mixed> $existing
     * @param array<string, mixed> $manifest
     * @param array<string, mixed>|null $schema
     * @return array<string, mixed>
     */
    private function syncExistingInstance(array $existing, array $manifest, ?array $schema): array
    {
        $currentSlug = (string) $existing['slug'];

        try {
            $this->typeGuard->assertTypeUnchanged($existing, $manifest);

            $installedVersion = (string) ($existing['manifest_json']['version'] ?? '');
            $availableVersion = (string) $manifest['version'];
            $result = version_compare($availableVersion, $installedVersion, '>')
                ? self::RESULT_OUTDATED
                : self::RESULT_UNCHANGED;

            if ($result === self::RESULT_UNCHANGED && in_array($manifest['type'] ?? '', ['entity', 'extension'], true)) {
                $installedSchema = $this->schemaCodec->decode($existing['schema_json'] ?? null);
                if ($installedSchema !== null) {
                    $this->installedSchemaValidator->assertContainsCanonical($currentSlug, $installedSchema, $schema);
                }
            }

            $this->backfillSchemaIfMissing($existing, $schema);

            return [
                'slug' => $currentSlug,
                'name' => (string) $manifest['label'],
                'plugin_type' => (string) $manifest['type'],
                'result' => $result,
                'installed_version' => $installedVersion,
                'available_version' => $availableVersion,
                'message' => $result === self::RESULT_OUTDATED
                    ? "Plugin '{$currentSlug}' has an update available."
                    : "Plugin '{$currentSlug}' is already synchronized.",
            ];
        } catch (Throwable $e) {
            return [
                'slug' => $currentSlug,
                'result' => self::RESULT_ERROR,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * @param array<string, mixed> $existing
     * @param array<string, mixed>|null $schema
     */
    private function backfillSchemaIfMissing(array $existing, ?array $schema): void
    {
        if ($schema === null) {
            return;
        }

        if ($this->schemaCodec->decode($existing['schema_json'] ?? null) !== null) {
            return;
        }

        $this->pluginWriteRepository->fillSchemaIfMissing((string) $existing['slug'], $schema);
    }
}
