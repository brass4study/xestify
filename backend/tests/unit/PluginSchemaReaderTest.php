<?php

declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__, 2));

require_once BASE_PATH . '/tests/unit/helpers.php';
require_once BASE_PATH . '/tests/helpers/plugins/plugin_fixtures.php';
require_once BASE_PATH . '/src/exceptions/PluginException.php';
require_once BASE_PATH . '/src/plugins/discovery/PluginSchemaReader.php';

use Xestify\exceptions\PluginException;
use Xestify\plugins\discovery\PluginSchemaReader;

const TEST_SCHEMA_VERSION = '1.0.0';

echo str_repeat('-', 40) . "\n";

TestSuite::run('read() returns null for extension plugins without schema.json', function (): void {
    $root = createPluginFixture([
        'slug' => 'extension_plugin',
        'name' => 'Extension',
        'version' => TEST_SCHEMA_VERSION,
        'type' => 'extension',
        'core_version' => TEST_SCHEMA_VERSION,
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

TestSuite::run('read() returns decoded schema for extension plugins when schema.json exists', function (): void {
    $slug = 'extension_with_schema';
    $root = createPluginFixture([
        'slug' => $slug,
        'name' => 'Extension With Schema',
        'version' => TEST_SCHEMA_VERSION,
        'type' => 'extension',
        'core_version' => TEST_SCHEMA_VERSION,
    ]);

    file_put_contents(
        $root . '/' . $slug . '/schema.json',
        (string) json_encode([
            'plugin' => $slug,
            'version' => TEST_SCHEMA_VERSION,
            'fields' => [
                'body' => ['type' => 'text', 'required' => true, 'label' => 'Body'],
            ],
        ], JSON_PRETTY_PRINT)
    );

    try {
        $reader = new PluginSchemaReader($root);
        $schema = $reader->read([
            'slug' => $slug,
            'type' => 'extension',
        ]);

        assertTrue(is_array($schema), 'Extension schema should be decoded when provided');
        assertTrue(isset($schema['fields']['body']), 'Extension schema must include body field');
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
            'version' => TEST_SCHEMA_VERSION,
            'type' => 'entity',
            'core_version' => TEST_SCHEMA_VERSION,
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

TestSuite::run('read() accepts an entity schema with an empty fields object', function (): void {
    $slug = 'entity_empty_fields';
    $root = createPluginFixture([
        'slug' => $slug,
        'name' => 'Empty Fields',
        'version' => TEST_SCHEMA_VERSION,
        'type' => 'entity',
        'core_version' => TEST_SCHEMA_VERSION,
    ]);

    file_put_contents(
        $root . '/' . $slug . '/schema.json',
        (string) json_encode([
            'entity' => $slug,
            'version' => TEST_SCHEMA_VERSION,
            'fields' => new stdClass(),
        ], JSON_PRETTY_PRINT)
    );

    try {
        $reader = new PluginSchemaReader($root);
        $schema = $reader->read(['slug' => $slug, 'type' => 'entity']);

        assertTrue(is_array($schema), 'Entity schema with no declared fields beyond identities/custom_fields must be accepted');
        assertEquals([], $schema['fields'], 'Decoded fields must stay an empty array, not be rejected as a list');
    } finally {
        removePluginFixture($root);
    }
});

TestSuite::run('read() returns decoded schema for entity plugins', function (): void {
    $slug = 'entity_valid_schema';
    $root = createPluginFixture([
        'slug' => $slug,
        'name' => 'Good Schema',
        'version' => TEST_SCHEMA_VERSION,
        'type' => 'entity',
        'core_version' => TEST_SCHEMA_VERSION,
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
