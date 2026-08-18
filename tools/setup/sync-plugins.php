<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

use Xestify\core\Database;
use Xestify\plugins\schema\InstalledPluginSchemaValidator;
use Xestify\plugins\lifecycle\PluginSyncService;
use Xestify\plugins\guards\PluginCompatibilityValidator;
use Xestify\plugins\guards\PluginDependencyValidator;
use Xestify\plugins\discovery\PluginDiscoveryService;
use Xestify\plugins\discovery\PluginManifestReader;
use Xestify\plugins\discovery\PluginSchemaCodec;
use Xestify\plugins\discovery\PluginSchemaReader;
use Xestify\plugins\discovery\PluginSourceService;
use Xestify\plugins\lifecycle\PluginClassLoader;
use Xestify\plugins\lifecycle\PluginLifecycleInvoker;
use Xestify\repositories\PluginRepository;
use Xestify\repositories\PluginWriteRepository;

$pluginsDir = dirname(__DIR__, 2) . '/plugins';
$pdo = Database::connection();
$pluginRepository = new PluginRepository($pdo, new PluginSchemaCodec());
$pluginSyncService = new PluginSyncService(
    $pdo,
    new PluginSourceService(
        new PluginDiscoveryService($pluginsDir),
        new PluginManifestReader($pluginsDir),
        new PluginSchemaReader($pluginsDir),
        new PluginCompatibilityValidator(),
        new PluginDependencyValidator($pluginRepository)
    ),
    $pluginRepository,
    new PluginWriteRepository($pdo, new PluginSchemaCodec()),
    new PluginLifecycleInvoker(new PluginClassLoader($pluginsDir, $pdo)),
    new PluginSchemaCodec(),
    new InstalledPluginSchemaValidator()
);

try {
    $result = $pluginSyncService->syncAll();
    $summary = $result['summary'];
    $plugins = $result['plugins'];

    echo "Plugin sync executed.\n";
    echo "Plugins discovered: {$summary['discovered']}\n";
    echo "Registered: {$summary['registered']}\n";
    echo "Unchanged: {$summary['unchanged']}\n";
    echo "Outdated: {$summary['outdated']}\n";
    echo "Errors: {$summary['errors']}\n";

    // syncAll() reports one entry per registered instance of each disk plugin
    // (plugin_name is not unique since STORY 10.3).
    foreach ($plugins as $pluginName => $instances) {
        foreach ($instances as $instance) {
            $slug = (string) ($instance['slug'] ?? $pluginName);
            $resultType = (string) ($instance['result'] ?? 'unknown');
            $installed = (string) ($instance['installed_version'] ?? 'n/a');
            $available = (string) ($instance['available_version'] ?? 'n/a');
            $message = (string) ($instance['message'] ?? '');
            echo "- {$pluginName} [{$slug}]: {$resultType} (installed={$installed}, available={$available}) {$message}\n";
        }
    }
} catch (\Throwable $e) {
    fwrite(STDERR, "Plugin sync failed: {$e->getMessage()}\n");
    exit(1);
}
