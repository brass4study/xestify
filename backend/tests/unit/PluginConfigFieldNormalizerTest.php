<?php

declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__, 2));

require_once BASE_PATH . '/tests/unit/helpers.php';
require_once BASE_PATH . '/src/plugins/application/PluginConfigFieldNormalizer.php';

use Xestify\plugins\application\PluginConfigFieldNormalizer;

$normalizer = new PluginConfigFieldNormalizer();

echo str_repeat('-', 40) . "\n";

TestSuite::run('normalizeFieldDefinition() trims and defaults optional fields', function () use ($normalizer): void {
    $field = $normalizer->normalizeFieldDefinition([
        'key' => '  phone  ',
        'type' => '  string  ',
        'label' => '  Phone  ',
    ]);

    assertEquals('phone', $field['key'], 'key must be trimmed');
    assertEquals('string', $field['type'], 'type must be trimmed');
    assertEquals('Phone', $field['label'], 'label must be trimmed');
    assertFalse($field['required'], 'required defaults to false when absent');
    assertTrue($field['summaryView'], 'summaryView defaults to true when absent');
});

TestSuite::run('normalizeFieldDefinition() rejects an empty key', function () use ($normalizer): void {
    $threw = false;
    try {
        $normalizer->normalizeFieldDefinition(['key' => '  ', 'label' => 'Phone']);
    } catch (InvalidArgumentException) {
        $threw = true;
    }

    assertTrue($threw, 'empty key must be rejected');
});

TestSuite::run('normalizeFieldDefinition() rejects an unsupported type', function () use ($normalizer): void {
    $threw = false;
    try {
        $normalizer->normalizeFieldDefinition(['key' => 'phone', 'label' => 'Phone', 'type' => 'not-a-type']);
    } catch (InvalidArgumentException) {
        $threw = true;
    }

    assertTrue($threw, 'unsupported type must be rejected');
});

TestSuite::run('normalizeFieldDefinition() rejects an empty label', function () use ($normalizer): void {
    $threw = false;
    try {
        $normalizer->normalizeFieldDefinition(['key' => 'phone', 'label' => '  ']);
    } catch (InvalidArgumentException) {
        $threw = true;
    }

    assertTrue($threw, 'empty label must be rejected');
});

TestSuite::run('normalizePayloadRows() normalizes each row and carries active/summaryView', function () use ($normalizer): void {
    $rows = $normalizer->normalizePayloadRows([
        ['key' => 'phone', 'label' => 'Phone', 'active' => true, 'summaryView' => false],
        ['key' => 'stock', 'label' => 'Stock', 'type' => 'number', 'active' => false],
    ]);

    assertEquals(2, count($rows), 'both rows must be normalized');
    assertTrue($rows[0]['active'], 'active must be carried from the payload row');
    assertFalse($rows[0]['summaryView'], 'summaryView must be carried from the payload row');
    assertFalse($rows[1]['active'], 'inactive row must stay inactive');
    assertEquals('number', $rows[1]['type'], 'type must be preserved');
});

TestSuite::run('normalizePayloadRows() rejects a non-object row', function () use ($normalizer): void {
    $threw = false;
    try {
        $normalizer->normalizePayloadRows(['not-an-object']);
    } catch (InvalidArgumentException) {
        $threw = true;
    }

    assertTrue($threw, 'a row that is not an object must be rejected');
});

TestSuite::run('orderedRows() respects ui_field_order and appends the rest', function () use ($normalizer): void {
    $rowsByKey = [
        'name' => ['key' => 'name'],
        'phone' => ['key' => 'phone'],
        'stock' => ['key' => 'stock'],
    ];

    $ordered = $normalizer->orderedRows($rowsByKey, ['ui_field_order' => ['stock', 'name']]);

    assertEquals(['stock', 'name', 'phone'], array_column($ordered, 'key'), 'ordered keys must follow ui_field_order first, then the rest');
});

TestSuite::run('orderedRows() ignores order entries that are not in rowsByKey', function () use ($normalizer): void {
    $rowsByKey = ['name' => ['key' => 'name']];

    $ordered = $normalizer->orderedRows($rowsByKey, ['ui_field_order' => ['ghost', 'name']]);

    assertEquals(['name'], array_column($ordered, 'key'), 'a stale order entry must be skipped, not crash or leave a gap');
});

TestSuite::run('orderedRows() falls back to insertion order when ui_field_order is missing', function () use ($normalizer): void {
    $rowsByKey = [
        'name' => ['key' => 'name'],
        'phone' => ['key' => 'phone'],
    ];

    $ordered = $normalizer->orderedRows($rowsByKey, []);

    assertEquals(['name', 'phone'], array_column($ordered, 'key'), 'without ui_field_order, rows must keep their original order');
});

echo str_repeat('-', 40) . "\n";
TestSuite::summary();
exit(TestSuite::exitCode());
