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

    foreach ($plugins as $slug => $plugin) {
        $resultType = is_array($plugin) && isset($plugin['result']) ? (string) $plugin['result'] : 'unknown';
        $installed = is_array($plugin) && isset($plugin['installed_version'])
            ? (string) $plugin['installed_version']
            : 'n/a';
        $available = is_array($plugin) && isset($plugin['available_version'])
            ? (string) $plugin['available_version']
            : 'n/a';
        $message = is_array($plugin) && isset($plugin['message']) ? (string) $plugin['message'] : '';
        echo "- {$slug}: {$resultType} (installed={$installed}, available={$available}) {$message}\n";
    }
} catch (\Throwable $e) {
    fwrite(STDERR, "Plugin sync failed: {$e->getMessage()}\n");
    exit(1);
}
