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
define('ORDERS_PLUGIN_DIR', BASE_PATH . '/plugins/orders');

// ---------------------------------------------------------------------------
// Tests
// ---------------------------------------------------------------------------

TestSuite::run('Plugin orders - manifest.json existe', function (): void {
    $path = ORDERS_PLUGIN_DIR . '/manifest.json';
    assertTrue(file_exists($path), 'manifest.json not found');
});

TestSuite::run('Plugin orders - manifest.json campos requeridos', function (): void {
    $data = json_decode((string) file_get_contents(ORDERS_PLUGIN_DIR . '/manifest.json'), true);
    assertTrue(is_array($data), 'manifest.json must be a JSON object');
    foreach (['name', 'label', 'version', 'type', 'core_version'] as $field) {
        assertTrue(isset($data[$field]) && $data[$field] !== '', "manifest.json missing field: {$field}");
    }
    assertTrue($data['name'] === 'orders', 'name must be orders');
    assertTrue($data['type'] === 'entity', 'type must be entity');
});

TestSuite::run('Plugin orders - schema.json existe', function (): void {
    $path = ORDERS_PLUGIN_DIR . '/schema.json';
    assertTrue(file_exists($path), 'schema.json not found');
});

TestSuite::run('Plugin orders - schema.json respeta contrato identities/fields/custom_fields/relations', function (): void {
    $data = json_decode((string) file_get_contents(ORDERS_PLUGIN_DIR . '/schema.json'), true);
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
    $expectedFieldKeys = ['order_date', 'status', 'total_amount'];
    sort($fieldKeys);
    sort($expectedFieldKeys);
    assertTrue($fieldKeys === $expectedFieldKeys, 'schema.json fields must match expected set exactly');

    assertTrue(($data['fields']['order_date']['type'] ?? '') === 'date', 'order_date must be date');
    assertTrue(($data['fields']['order_date']['required'] ?? false) === true, 'order_date must be required');

    assertTrue(($data['fields']['status']['type'] ?? '') === 'select', 'status must be select');
    assertTrue(($data['fields']['status']['required'] ?? false) === true, 'status must be required');
    $statusValues = array_map(
        static fn (array $option): mixed => $option['value'] ?? null,
        $data['fields']['status']['options'] ?? []
    );
    $expectedStatusValues = ['pendiente', 'en_proceso', 'entregado', 'cancelado'];
    sort($statusValues);
    sort($expectedStatusValues);
    assertTrue($statusValues === $expectedStatusValues, 'status options must match expected set exactly');

    assertTrue(($data['fields']['total_amount']['type'] ?? '') === 'number', 'total_amount must be number');
    assertTrue(($data['fields']['total_amount']['required'] ?? false) === true, 'total_amount must be required');

    $customFieldsByKey = [];
    foreach ($data['custom_fields'] as $customField) {
        assertTrue(is_array($customField), 'each custom_field must be an object');
        assertTrue(isset($customField['key']) && is_string($customField['key']) && $customField['key'] !== '', 'custom_field.key is required');
        $customFieldsByKey[$customField['key']] = $customField;
    }
    assertTrue(array_keys($customFieldsByKey) === ['notes'], 'custom_fields keys must match expected set exactly');
    assertTrue(($customFieldsByKey['notes']['type'] ?? '') === 'text', 'notes custom_field must be text');
    assertTrue(($customFieldsByKey['notes']['required'] ?? true) === false, 'notes custom_field must be optional');

    // The disk schema deliberately ships no relations: which real entity an
    // order belongs to (e.g. a specific persons instance like "distributors")
    // is a per-installation decision made later via PluginConfig's
    // "Relaciones" grid, not something the shared template should hardcode.
    assertTrue($data['relations'] === [], 'orders must not hardcode a relation in the disk schema');
});

TestSuite::run('Plugin orders - no declara Hooks.php (sin restricciones de unicidad)', function (): void {
    assertTrue(!file_exists(ORDERS_PLUGIN_DIR . '/Hooks.php'), 'orders should not have a Hooks.php file');
});

// ---------------------------------------------------------------------------
TestSuite::summary();
exit(TestSuite::exitCode());
