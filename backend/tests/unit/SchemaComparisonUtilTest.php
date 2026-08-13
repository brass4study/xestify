<?php

declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__, 2));

require_once BASE_PATH . '/tests/unit/helpers.php';
require_once BASE_PATH . '/src/plugins/schema/SchemaComparisonUtil.php';

use Xestify\plugins\schema\SchemaComparisonUtil;

$util = new SchemaComparisonUtil();

echo str_repeat('-', 40) . "\n";

TestSuite::run('normalize() makes key order irrelevant for associative arrays', function () use ($util): void {
    $a = ['type' => 'string', 'required' => true, 'label' => 'Name'];
    $b = ['label' => 'Name', 'type' => 'string', 'required' => true];

    assertEquals($util->normalize($a), $util->normalize($b), 'Same keys/values in different order must normalize equal');
});

TestSuite::run('normalize() recurses into nested associative arrays', function () use ($util): void {
    $a = ['field' => ['type' => 'string', 'meta' => ['b' => 2, 'a' => 1]]];
    $b = ['field' => ['meta' => ['a' => 1, 'b' => 2], 'type' => 'string']];

    assertEquals($util->normalize($a), $util->normalize($b), 'Nested associative arrays must normalize regardless of key order at any depth');
});

TestSuite::run('normalize() preserves list order (does not sort sequences)', function () use ($util): void {
    $list = ['b', 'a', 'c'];

    assertEquals(['b', 'a', 'c'], $util->normalize($list), 'Lists (sequential arrays) must keep their original order');
});

TestSuite::run('normalize() detects a real difference after normalization', function () use ($util): void {
    $a = ['type' => 'string', 'required' => true];
    $b = ['type' => 'string', 'required' => false];

    assertTrue($util->normalize($a) !== $util->normalize($b), 'A real value difference must remain a difference after normalization');
});

TestSuite::run('normalize() returns scalars unchanged', function () use ($util): void {
    assertEquals('clients', $util->normalize('clients'), 'Scalar strings must pass through unchanged');
    assertEquals(42, $util->normalize(42), 'Scalar ints must pass through unchanged');
});

echo str_repeat('-', 40) . "\n";
TestSuite::summary();
exit(TestSuite::exitCode());
