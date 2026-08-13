<?php

declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__, 2));

require_once BASE_PATH . '/tests/unit/helpers.php';
require_once BASE_PATH . '/tests/helpers/plugins/plugin_fixtures.php';
require_once BASE_PATH . '/src/exceptions/PluginException.php';
require_once BASE_PATH . '/src/plugins/discovery/PluginManifestReader.php';

use Xestify\exceptions\PluginException;
use Xestify\plugins\discovery\PluginManifestReader;

const MANIFEST_VERSION = '1.0.0';

echo str_repeat('-', 40) . "\n";

TestSuite::run('read() throws PluginException when manifest.json is missing', function (): void {
    $root = sys_get_temp_dir() . '/xestify_plugin_nomf_' . bin2hex(random_bytes(4));
    mkdir($root . '/no_manifest', 0777, true);

    try {
        $reader = new PluginManifestReader($root);
        $threw = false;

        try {
            $reader->read('no_manifest');
        } catch (PluginException) {
            $threw = true;
        }

        assertTrue($threw, 'Should throw PluginException for missing manifest');
    } finally {
        removePluginFixture($root);
    }
});

TestSuite::run('read() throws PluginException when manifest has invalid JSON', function (): void {
    $root = createPluginFixture(['slug' => 'bad_json'], false, true);

    try {
        $reader = new PluginManifestReader($root);
        $threw = false;

        try {
            $reader->read('bad_json');
        } catch (PluginException) {
            $threw = true;
        }

        assertTrue($threw, 'Should throw PluginException for invalid JSON');
    } finally {
        removePluginFixture($root);
    }
});

TestSuite::run('read() validates required fields and type', function (): void {
    $root = createPluginFixture([
        'slug' => 'bad_type',
        'name' => 'Bad Type',
        'version' => MANIFEST_VERSION,
        'type' => 'unknown',
        'core_version' => MANIFEST_VERSION,
    ]);

    try {
        $reader = new PluginManifestReader($root);
        $threw = false;

        try {
            $reader->read('bad_type');
        } catch (PluginException) {
            $threw = true;
        }

        assertTrue($threw, 'Should reject unsupported plugin type');
    } finally {
        removePluginFixture($root);
    }
});

TestSuite::run('read() returns manifest when structure is valid', function (): void {
    $manifest = [
        'slug' => 'valid_manifest',
        'name' => 'Valid Manifest',
        'version' => MANIFEST_VERSION,
        'type' => 'entity',
        'core_version' => MANIFEST_VERSION,
    ];
    $root = createPluginFixture($manifest);

    try {
        $reader = new PluginManifestReader($root);
        $loaded = $reader->read('valid_manifest');
        assertEquals('valid_manifest', $loaded['slug'] ?? null, 'slug should match');
    } finally {
        removePluginFixture($root);
    }
});

echo str_repeat('-', 40) . "\n";
TestSuite::summary();
exit(TestSuite::exitCode());
