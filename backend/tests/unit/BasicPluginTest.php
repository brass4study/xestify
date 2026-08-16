<?php

declare(strict_types=1);

// ---------------------------------------------------------------------------
// Bootstrap
// ---------------------------------------------------------------------------
define('BACKEND_PATH', dirname(__DIR__, 2));
define('BASE_PATH', dirname(BACKEND_PATH));

require_once __DIR__ . '/helpers.php';

// ---------------------------------------------------------------------------
// PLUGIN_DIR constant for structure tests
// ---------------------------------------------------------------------------
define('BASIC_PLUGIN_DIR', BASE_PATH . '/plugins/basic');

// ---------------------------------------------------------------------------
// Tests
// ---------------------------------------------------------------------------

TestSuite::run('Plugin basic - manifest.json existe', function (): void {
    $path = BASIC_PLUGIN_DIR . '/manifest.json';
    assertTrue(file_exists($path), 'manifest.json not found');
});

TestSuite::run('Plugin basic - manifest.json campos requeridos', function (): void {
    $data = json_decode((string) file_get_contents(BASIC_PLUGIN_DIR . '/manifest.json'), true);
    assertTrue(is_array($data), 'manifest.json must be a JSON object');
    foreach (['name', 'label', 'version', 'type', 'core_version'] as $field) {
        assertTrue(isset($data[$field]) && $data[$field] !== '', "manifest.json missing field: {$field}");
    }
    assertTrue($data['name'] === 'basic', 'name must be basic');
    assertTrue($data['type'] === 'entity', 'type must be entity');
});

TestSuite::run('Plugin basic - schema.json existe', function (): void {
    $path = BASIC_PLUGIN_DIR . '/schema.json';
    assertTrue(file_exists($path), 'schema.json not found');
});

TestSuite::run('Plugin basic - schema.json respeta contrato identities/fields/custom_fields/relations', function (): void {
    $data = json_decode((string) file_get_contents(BASIC_PLUGIN_DIR . '/schema.json'), true);
    assertTrue(is_array($data), 'schema.json must be a JSON object');
    assertTrue(isset($data['identities']) && is_array($data['identities']), 'schema.json must have "identities"');
    assertTrue(isset($data['fields']) && is_array($data['fields']), 'schema.json must have "fields"');
    assertTrue(isset($data['custom_fields']) && is_array($data['custom_fields']), 'schema.json must have "custom_fields"');
    assertTrue(isset($data['relations']) && is_array($data['relations']), 'schema.json must have "relations"');

    assertTrue(isset($data['identities']['id']), 'schema.json must define identity "id"');
    assertTrue(($data['identities']['id']['type'] ?? '') === 'uuid', 'identity id must be uuid');
    assertTrue(($data['identities']['id']['auto_generated'] ?? false) === true, 'identity id must be auto_generated');
    assertTrue(($data['identities']['id']['editable'] ?? true) === false, 'identity id must be non-editable');

    $fieldKeys = array_keys($data['fields']);
    assertTrue($fieldKeys === ['name'], 'basic must declare exactly the "name" field');
    assertTrue(($data['fields']['name']['type'] ?? '') === 'string', 'name must be string');
    assertTrue(($data['fields']['name']['required'] ?? false) === true, 'name must be required');

    assertTrue($data['custom_fields'] === [], 'basic must not declare custom_fields');
    assertTrue($data['relations'] === [], 'basic must not declare relations');
});

TestSuite::run('Plugin basic - no declara Hooks.php (sin restricciones de unicidad)', function (): void {
    assertTrue(!file_exists(BASIC_PLUGIN_DIR . '/Hooks.php'), 'basic should not have a Hooks.php file');
});

// ---------------------------------------------------------------------------
TestSuite::summary();
exit(TestSuite::exitCode());
