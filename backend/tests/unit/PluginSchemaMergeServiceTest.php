<?php

declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__, 2));

require_once BASE_PATH . '/tests/unit/helpers.php';
require_once BASE_PATH . '/src/exceptions/PluginException.php';
require_once BASE_PATH . '/src/plugins/schema/SchemaComparisonUtil.php';
require_once BASE_PATH . '/src/plugins/schema/PluginSchemaMergeService.php';

use Xestify\plugins\schema\PluginSchemaMergeService;

$service = new PluginSchemaMergeService();

echo str_repeat('-', 40) . "\n";

TestSuite::run('mergeAdditively() adds new field and custom field', function () use ($service): void {
    $result = $service->mergeAdditively(
        'persons',
        [
            'entity' => 'persons',
            'fields' => [
                'name' => ['type' => 'string', 'required' => true],
            ],
            'custom_fields' => [],
            'relations' => [],
        ],
        [
            'entity' => 'persons',
            'fields' => [
                'name' => ['type' => 'string', 'required' => true],
                'email' => ['type' => 'email', 'required' => false],
            ],
            'custom_fields' => [
                ['key' => 'phone', 'type' => 'string'],
            ],
            'relations' => [],
        ]
    );

    assertTrue($result['changed'] === true, 'Merge should detect additive changes');
    assertTrue(in_array('email', $result['diff']['fields']['added'], true), 'email should be added');
    assertTrue(
        in_array('phone', $result['diff']['custom_fields']['added'], true),
        'phone custom field should be added'
    );
});

TestSuite::run('mergeAdditively() fails on non-additive field change', function () use ($service): void {
    $threw = false;

    try {
        $service->mergeAdditively(
            'persons',
            [
                'entity' => 'persons',
                'fields' => [
                    'name' => ['type' => 'string', 'required' => true],
                ],
                'custom_fields' => [],
                'relations' => [],
            ],
            [
                'entity' => 'persons',
                'fields' => [
                    'name' => ['type' => 'text', 'required' => true],
                ],
                'custom_fields' => [],
                'relations' => [],
            ]
        );
    } catch (DomainException) {
        $threw = true;
    }

    assertTrue($threw, 'Non-additive changes must fail');
});

TestSuite::run('mergeAdditively() does not treat an edited active custom field as a breaking change once configured', function () use ($service): void {
    // Once saveConfig() has run, custom_fields means "active fields" (which the
    // admin may have edited after activating a suggestion) and the canonical
    // catalog moves to plugin_suggested_custom_fields. An update must compare
    // against the catalog, not the edited active copy, or this throws.
    $result = $service->mergeAdditively(
        'persons',
        [
            'entity' => 'persons',
            'fields' => ['name' => ['type' => 'string', 'required' => true]],
            'custom_fields' => [
                ['key' => 'phone', 'type' => 'string', 'label' => 'Telefono movil'],
            ],
            'plugin_suggested_custom_fields' => [
                ['key' => 'phone', 'type' => 'string', 'label' => 'Telefono'],
            ],
            'relations' => [],
        ],
        [
            'entity' => 'persons',
            'fields' => ['name' => ['type' => 'string', 'required' => true]],
            'custom_fields' => [
                ['key' => 'phone', 'type' => 'string', 'label' => 'Telefono'],
            ],
            'relations' => [],
        ]
    );

    assertEquals(
        'Telefono movil',
        $result['schema']['custom_fields'][0]['label'] ?? null,
        'Active custom_fields must be left untouched by the merge'
    );
});

TestSuite::run('mergeAdditively() adds a new suggested field to the catalog, not to active custom_fields', function () use ($service): void {
    $result = $service->mergeAdditively(
        'persons',
        [
            'entity' => 'persons',
            'fields' => ['name' => ['type' => 'string', 'required' => true]],
            'custom_fields' => [],
            'plugin_suggested_custom_fields' => [
                ['key' => 'phone', 'type' => 'string', 'label' => 'Telefono'],
            ],
            'relations' => [],
        ],
        [
            'entity' => 'persons',
            'fields' => ['name' => ['type' => 'string', 'required' => true]],
            'custom_fields' => [
                ['key' => 'phone', 'type' => 'string', 'label' => 'Telefono'],
                ['key' => 'stock', 'type' => 'number', 'label' => 'Stock'],
            ],
            'relations' => [],
        ]
    );

    assertEquals([], $result['schema']['custom_fields'], 'A new suggested field must not auto-activate');
    assertTrue(
        in_array('stock', array_column($result['schema']['plugin_suggested_custom_fields'], 'key'), true),
        'The new field must land in the suggested catalog'
    );
    assertTrue(
        in_array('stock', $result['diff']['custom_fields']['added'], true),
        'The diff should report stock as added'
    );
});

echo str_repeat('-', 40) . "\n";
TestSuite::summary();
exit(TestSuite::exitCode());
