<?php

declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__, 2));

require_once BASE_PATH . '/tests/unit/helpers.php';
require_once BASE_PATH . '/tests/unit/validation_bootstrap.php';

use Xestify\services\ValidationService;

$service = new ValidationService();

/**
 * @param list<array{field: string, code: string, message: string}> $errors
 */
function findValidationError(array $errors, string $field, string $code): ?array
{
    foreach ($errors as $error) {
        if (($error['field'] ?? '') === $field && ($error['code'] ?? '') === $code) {
            return $error;
        }
    }

    return null;
}

TestSuite::run('validate returns valid result for valid payload', function () use ($service): void {
    $schema = [
        'fields' => [
            'name' => ['type' => 'string', 'required' => true, 'minLength' => 2, 'maxLength' => 100],
            'age' => ['type' => 'number', 'required' => true, 'min' => 18, 'max' => 99],
            'email' => ['type' => 'mail', 'required' => true],
            'isActive' => ['type' => 'boolean', 'required' => true],
            'birthDate' => ['type' => 'date', 'required' => false],
            'status' => ['type' => 'select', 'required' => true, 'options' => ['draft', 'published']],
        ],
    ];

    $data = [
        'name' => 'Ana',
        'age' => 32,
        'email' => 'ana@example.com',
        'isActive' => true,
        'birthDate' => '1992-02-20',
        'status' => 'draft',
    ];

    $result = $service->validate($data, $schema);

    assertTrue($result->isValid(), 'Expected valid result');
    assertEquals([], $result->errors(), 'Expected no validation errors');
});

TestSuite::run('validate reports missing required field with structured error', function () use ($service): void {
    $schema = [
        'fields' => [
            'name' => ['type' => 'string', 'required' => true],
        ],
    ];

    $result = $service->validate([], $schema);
    $error = findValidationError($result->errors(), 'name', 'required');

    assertFalse($result->isValid(), 'Expected invalid result');
    assertEquals('Field is required', $error['message'] ?? null);
});

TestSuite::run('validate reports unknown field type as schema error', function () use ($service): void {
    $schema = [
        'fields' => [
            'payload' => ['type' => 'json_blob', 'required' => true],
        ],
    ];

    $result = $service->validate(['payload' => '{}'], $schema);
    $error = findValidationError($result->errors(), 'payload', 'unknown_type');

    assertFalse($result->isValid(), 'Expected invalid result for unknown type');
    assertEquals('Unsupported type: json_blob', $error['message'] ?? null);
});

TestSuite::run('validate reports invalid type', function () use ($service): void {
    $schema = [
        'fields' => [
            'isActive' => ['type' => 'boolean', 'required' => true],
        ],
    ];

    $result = $service->validate(['isActive' => 'yes'], $schema);
    $error = findValidationError($result->errors(), 'isActive', 'invalid_type');

    assertEquals('Expected boolean', $error['message'] ?? null);
});

TestSuite::run('validate supports text fields', function () use ($service): void {
    $schema = [
        'fields' => [
            'description' => ['type' => 'text', 'required' => true, 'minLength' => 3],
        ],
    ];

    $validResult = $service->validate(['description' => 'Producto nuevo'], $schema);
    assertTrue($validResult->isValid(), 'Expected text field to be valid');

    $invalidResult = $service->validate(['description' => 123], $schema);
    $error = findValidationError($invalidResult->errors(), 'description', 'invalid_type');
    assertEquals('Expected text', $error['message'] ?? null);
});

TestSuite::run('validate reports invalid email', function () use ($service): void {
    $schema = [
        'fields' => [
            'email' => ['type' => 'mail', 'required' => true],
        ],
    ];

    $result = $service->validate(['email' => 'invalid_mail'], $schema);
    $error = findValidationError($result->errors(), 'email', 'invalid_mail');

    assertEquals('Invalid email', $error['message'] ?? null);
});

TestSuite::run('validate checks min and max length for strings', function () use ($service): void {
    $schema = [
        'fields' => [
            'title' => ['type' => 'string', 'required' => true, 'minLength' => 3, 'maxLength' => 5],
        ],
    ];

    $shortResult = $service->validate(['title' => 'AB'], $schema);
    $shortError = findValidationError($shortResult->errors(), 'title', 'min_length');
    assertEquals('Minimum length is 3', $shortError['message'] ?? null);

    $longResult = $service->validate(['title' => 'ABCDEF'], $schema);
    $longError = findValidationError($longResult->errors(), 'title', 'max_length');
    assertEquals('Maximum length is 5', $longError['message'] ?? null);
});

TestSuite::run('validate checks min and max range for numbers', function () use ($service): void {
    $schema = [
        'fields' => [
            'qty' => ['type' => 'number', 'required' => true, 'min' => 1, 'max' => 10],
        ],
    ];

    $belowResult = $service->validate(['qty' => 0], $schema);
    $belowError = findValidationError($belowResult->errors(), 'qty', 'min_value');
    assertEquals('Minimum value is 1', $belowError['message'] ?? null);

    $aboveResult = $service->validate(['qty' => 11], $schema);
    $aboveError = findValidationError($aboveResult->errors(), 'qty', 'max_value');
    assertEquals('Maximum value is 10', $aboveError['message'] ?? null);
});

TestSuite::run('validate checks select options', function () use ($service): void {
    $schema = [
        'fields' => [
            'status' => ['type' => 'select', 'required' => true, 'options' => ['draft', 'published']],
        ],
    ];

    $result = $service->validate(['status' => 'archived'], $schema);
    $error = findValidationError($result->errors(), 'status', 'invalid_option');

    assertEquals('Value not allowed', $error['message'] ?? null);
});

TestSuite::run('validate supports list style schema field definitions', function () use ($service): void {
    $schema = [
        'fields' => [
            ['name' => 'slug', 'type' => 'string', 'required' => true],
            ['name' => 'priority', 'type' => 'number', 'required' => true],
        ],
    ];

    $result = $service->validate(['slug' => 'ok', 'priority' => 5], $schema);

    assertTrue($result->isValid(), 'Expected no errors for list style fields');
});

TestSuite::run('validate supports custom_fields definitions', function () use ($service): void {
    $schema = [
        'fields' => [
            'name' => ['type' => 'string', 'required' => true],
        ],
        'custom_fields' => [
            ['key' => 'phone', 'type' => 'string', 'required' => true],
            ['key' => 'creation_stamp', 'type' => 'timestamp', 'required' => false],
            ['key' => 'is_active', 'type' => 'boolean', 'required' => true],
        ],
    ];

    $validResult = $service->validate([
        'name' => 'Cliente',
        'phone' => '+34 600 000 000',
        'creation_stamp' => 'now',
        'is_active' => true,
    ], $schema);
    assertTrue($validResult->isValid(), 'Expected no errors for valid custom_fields');

    $missingResult = $service->validate(['name' => 'Cliente'], $schema);
    assertTrue(
        findValidationError($missingResult->errors(), 'phone', 'required') !== null,
        'Expected required error for custom field phone'
    );
    assertTrue(
        findValidationError($missingResult->errors(), 'is_active', 'required') !== null,
        'Expected required error for custom field is_active'
    );

    $typeResult = $service->validate([
        'name' => 'Cliente',
        'phone' => '+34 600 000 000',
        'is_active' => 'yes',
    ], $schema);
    $error = findValidationError($typeResult->errors(), 'is_active', 'invalid_type');
    assertEquals('Expected boolean', $error['message'] ?? null);
});

TestSuite::run('validate rejects keys not declared in the schema', function () use ($service): void {
    $schema = [
        'fields' => [
            'name' => ['type' => 'string', 'required' => true],
        ],
    ];

    $result = $service->validate(['name' => 'Ana', 'is_admin' => true], $schema);
    $error = findValidationError($result->errors(), 'is_admin', 'unknown_field');

    assertFalse($result->isValid(), 'Expected invalid result for an undeclared field');
    assertEquals('Unknown field: is_admin', $error['message'] ?? null);
});

TestSuite::run('validate allows relation keys through without type validation', function () use ($service): void {
    $schema = [
        'fields' => [
            'name' => ['type' => 'string', 'required' => true],
        ],
        'relations' => [
            ['key' => 'id_person', 'type' => 'belongs_to', 'target_entity' => 'persons', 'required' => false],
        ],
    ];

    $result = $service->validate(['name' => 'Pedido', 'id_person' => 'not-a-validated-uuid'], $schema);

    assertTrue($result->isValid(), 'Expected relation keys to be accepted without type checks');
});

TestSuite::summary();
exit(TestSuite::exitCode());
