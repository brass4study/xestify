<?php

declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__, 2));

require_once BASE_PATH . '/tests/unit/helpers.php';
require_once BASE_PATH . '/src/exceptions/PluginException.php';
require_once BASE_PATH . '/src/plugins/application/PluginSchemaMergeService.php';

use Xestify\plugins\application\PluginSchemaMergeService;

$service = new PluginSchemaMergeService();

echo str_repeat('-', 40) . "\n";

TestSuite::run('mergeAdditively() adds new field and custom field', function () use ($service): void {
    $result = $service->mergeAdditively(
        'clients',
        [
            'entity' => 'clients',
            'fields' => [
                'name' => ['type' => 'string', 'required' => true],
            ],
            'custom_fields' => [],
            'relations' => [],
        ],
        [
            'entity' => 'clients',
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
            'clients',
            [
                'entity' => 'clients',
                'fields' => [
                    'name' => ['type' => 'string', 'required' => true],
                ],
                'custom_fields' => [],
                'relations' => [],
            ],
            [
                'entity' => 'clients',
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

TestSuite::run('mergeAdditively() fails when entity identifier changes', function () use ($service): void {
    $threw = false;

    try {
        $service->mergeAdditively(
            'clients',
            ['entity' => 'clients', 'fields' => [], 'custom_fields' => [], 'relations' => []],
            ['entity' => 'contacts', 'fields' => [], 'custom_fields' => [], 'relations' => []]
        );
    } catch (DomainException) {
        $threw = true;
    }

    assertTrue($threw, 'Changing entity identifier must fail');
});

echo str_repeat('-', 40) . "\n";
TestSuite::summary();
exit(TestSuite::exitCode());
