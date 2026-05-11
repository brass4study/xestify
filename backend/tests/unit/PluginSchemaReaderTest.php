<?php

declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__, 2));

require_once BASE_PATH . '/tests/unit/helpers.php';
require_once BASE_PATH . '/tests/helpers/plugins/plugin_fixtures.php';
require_once BASE_PATH . '/src/exceptions/PluginException.php';
require_once BASE_PATH . '/src/plugins/infrastructure/PluginSchemaReader.php';

use Xestify\exceptions\PluginException;
use Xestify\plugins\infrastructure\PluginSchemaReader;

echo str_repeat('-', 40) . "\n";

TestSuite::run('read() returns null for extension plugins', function (): void {
    $root = createPluginFixture([
        'slug' => 'extension_plugin',
        'name' => 'Extension',
        'version' => '1.0.0',
        'type' => 'extension',
        'core_version' => '1.0.0',
    ]);

    try {
        $reader = new PluginSchemaReader($root);
        $schema = $reader->read([
            'slug' => 'extension_plugin',
            'type' => 'extension',
        ]);
        assertNull($schema, 'Extension plugins should not require schema');
    } finally {
        removePluginFixture($root);
    }
});

TestSuite::run('read() throws PluginException when entity plugin lacks schema.json', function (): void {
    $slug = 'entity_no_schema';
    $root = sys_get_temp_dir() . '/xestify_schema_' . bin2hex(random_bytes(4));
    mkdir($root . '/' . $slug, 0777, true);
    file_put_contents($root . '/' . $slug . '/manifest.json', '{"slug":"entity_no_schema"}');

    try {
        $reader = new PluginSchemaReader($root);
        $threw = false;

        try {
            $reader->read(['slug' => $slug, 'type' => 'entity']);
        } catch (PluginException) {
            $threw = true;
        }

        assertTrue($threw, 'Entity plugin without schema must fail');
    } finally {
        removePluginFixture($root);
    }
});

TestSuite::run('read() throws PluginException when schema lacks fields', function (): void {
    $slug = 'entity_bad_schema';
    $root = createPluginFixture(
        [
            'slug' => $slug,
            'name' => 'Bad Schema',
            'version' => '1.0.0',
            'type' => 'entity',
            'core_version' => '1.0.0',
        ],
        false,
        false,
        ['entity' => $slug]
    );

    try {
        $reader = new PluginSchemaReader($root);
        $threw = false;

        try {
            $reader->read(['slug' => $slug, 'type' => 'entity']);
        } catch (PluginException) {
            $threw = true;
        }

        assertTrue($threw, 'Schema without fields must be rejected');
    } finally {
        removePluginFixture($root);
    }
});

TestSuite::run('read() returns decoded schema for entity plugins', function (): void {
    $slug = 'entity_valid_schema';
    $root = createPluginFixture([
        'slug' => $slug,
        'name' => 'Good Schema',
        'version' => '1.0.0',
        'type' => 'entity',
        'core_version' => '1.0.0',
    ]);

    try {
        $reader = new PluginSchemaReader($root);
        $schema = $reader->read(['slug' => $slug, 'type' => 'entity']);
        assertEquals($slug, $schema['entity'] ?? null, 'entity should match slug');
        assertTrue(isset($schema['fields']['name']), 'Default fixture should include name field');
    } finally {
        removePluginFixture($root);
    }
});

echo str_repeat('-', 40) . "\n";
TestSuite::summary();
exit(TestSuite::exitCode());
