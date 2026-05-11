<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

use Xestify\core\Database;
use Xestify\plugins\application\InstalledPluginSchemaValidator;
use Xestify\plugins\application\PluginSyncService;
use Xestify\plugins\infrastructure\PluginCompatibilityValidator;
use Xestify\plugins\infrastructure\PluginDependencyValidator;
use Xestify\plugins\infrastructure\PluginDiscoveryService;
use Xestify\plugins\infrastructure\PluginManifestReader;
use Xestify\plugins\infrastructure\PluginSchemaCodec;
use Xestify\plugins\infrastructure\PluginSchemaReader;
use Xestify\plugins\infrastructure\PluginSourceService;
use Xestify\plugins\infrastructure\PluginClassLoader;
use Xestify\plugins\runtime\PluginLifecycleInvoker;
use Xestify\repositories\PluginRepository;

$pluginsDir = dirname(__DIR__, 2) . '/plugins';
$pdo = Database::connection();
$pluginRepository = new PluginRepository($pdo, new PluginSchemaCodec());
$pluginSyncService = new PluginSyncService(
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
