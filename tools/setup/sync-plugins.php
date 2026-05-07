<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

use Xestify\plugins\PluginLoader;

$loader = new PluginLoader(
    dirname(__DIR__, 2) . '/plugins',
    Xestify\core\Database::connection()
);

try {
    $loaded = $loader->loadAll();
    $count = count($loaded);

    echo "Plugin sync executed.\n";
    echo "Plugins synchronized: {$count}\n";

    foreach ($loaded as $slug => $manifest) {
        $version = is_array($manifest) && isset($manifest['version']) ? (string) $manifest['version'] : 'unknown';
        echo "- {$slug} ({$version})\n";
    }
} catch (\Throwable $e) {
    fwrite(STDERR, "Plugin sync failed: {$e->getMessage()}\n");
    exit(1);
}
