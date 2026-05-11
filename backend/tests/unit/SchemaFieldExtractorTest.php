<?php

declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__, 2));

require_once BASE_PATH . '/tests/unit/helpers.php';
require_once BASE_PATH . '/tests/unit/validation_bootstrap.php';

use Xestify\validation\schema\SchemaFieldExtractor;

$extractor = new SchemaFieldExtractor();

TestSuite::run('extract() includes fields definitions', function () use ($extractor): void {
    $fields = $extractor->extract([
        'fields' => [
            'name' => ['type' => 'string'],
            ['name' => 'email', 'type' => 'email'],
        ],
    ]);

    assertTrue(isset($fields['name']), 'Expected keyed field name');
    assertTrue(isset($fields['email']), 'Expected list-style field email');
});

TestSuite::run('extract() includes custom_fields using key as technical name', function () use ($extractor): void {
    $fields = $extractor->extract([
        'custom_fields' => [
            ['key' => 'phone', 'type' => 'string'],
            ['key' => 'is_active', 'type' => 'boolean'],
        ],
    ]);

    assertEquals('string', $fields['phone']['type'] ?? null);
    assertEquals('boolean', $fields['is_active']['type'] ?? null);
});

TestSuite::run('extract() skips non editable or auto generated identities', function () use ($extractor): void {
    $fields = $extractor->extract([
        'identities' => [
            ['key' => 'id', 'type' => 'uuid', 'auto_generated' => true],
            ['key' => 'external_id', 'type' => 'string', 'editable' => false],
            ['key' => 'tax_id', 'type' => 'string', 'editable' => true],
        ],
    ]);

    assertFalse(isset($fields['id']), 'Auto generated id must be skipped');
    assertFalse(isset($fields['external_id']), 'Non editable identity must be skipped');
    assertTrue(isset($fields['tax_id']), 'Editable identity can be validated');
});

TestSuite::summary();
exit(TestSuite::exitCode());
