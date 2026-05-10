<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

use Xestify\plugins\PluginLoader;

$loader = new PluginLoader(
    dirname(__DIR__, 2) . '/plugins',
    Xestify\core\Database::connection()
);

try {
    $result = $loader->syncAll();
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
