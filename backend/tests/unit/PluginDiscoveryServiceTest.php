<?php

declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__, 2));

require_once BASE_PATH . '/tests/unit/helpers.php';
require_once BASE_PATH . '/tests/helpers/plugins/plugin_fixtures.php';
require_once BASE_PATH . '/src/plugins/discovery/PluginDiscoveryService.php';

use Xestify\plugins\discovery\PluginDiscoveryService;

echo str_repeat('-', 40) . "\n";

TestSuite::run('discover() returns empty array when plugins dir does not exist', function (): void {
    $service = new PluginDiscoveryService('/nonexistent/path/xyz');
    assertEquals([], $service->discover(), 'Should return empty array');
});

TestSuite::run('discover() returns plugin slug when manifest exists', function (): void {
    $manifest = [
        'name' => 'test_disc',
        'label' => 'Test',
        'version' => '1.0.0',
        'type' => 'entity',
        'core_version' => '1.0.0',
    ];
    $root = createPluginFixture($manifest);

    try {
        $service = new PluginDiscoveryService($root);
        $slugs = $service->discover();
        assertTrue(in_array('test_disc', $slugs, true), 'discover() should return plugin slug');
    } finally {
        removePluginFixture($root);
    }
});

TestSuite::run('discover() ignores directories without manifest', function (): void {
    $root = sys_get_temp_dir() . '/xestify_plugin_discovery_' . bin2hex(random_bytes(4));
    mkdir($root . '/empty', 0777, true);

    try {
        $service = new PluginDiscoveryService($root);
        assertEquals([], $service->discover(), 'Folders without manifest should be ignored');
    } finally {
        removePluginFixture($root);
    }
});

echo str_repeat('-', 40) . "\n";
TestSuite::summary();
exit(TestSuite::exitCode());
