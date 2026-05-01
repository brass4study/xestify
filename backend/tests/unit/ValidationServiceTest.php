<?php

declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__, 2));

require_once BASE_PATH . '/tests/unit/helpers.php';
require_once BASE_PATH . '/src/services/ValidationService.php';

use Xestify\services\ValidationService;

$service = new ValidationService();

TestSuite::run('validate returns no errors for valid payload', function () use ($service): void {
    $schema = [
        'fields' => [
            'name' => ['type' => 'string', 'required' => true, 'minLength' => 2, 'maxLength' => 100],
            'age' => ['type' => 'number', 'required' => true, 'min' => 18, 'max' => 99],
            'email' => ['type' => 'email', 'required' => true],
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

    $errors = $service->validate($data, $schema);
    assertEquals([], $errors, 'Expected no validation errors');
});

TestSuite::run('validate reports missing required field', function () use ($service): void {
    $schema = [
        'fields' => [
            'name' => ['type' => 'string', 'required' => true],
        ],
    ];

    $errors = $service->validate([], $schema);

    assertTrue(isset($errors['name']), 'Expected required error for name');
    assertEquals('Field is required', $errors['name'][0] ?? null);
});

TestSuite::run('validate reports invalid type', function () use ($service): void {
    $schema = [
        'fields' => [
            'isActive' => ['type' => 'boolean', 'required' => true],
        ],
    ];

    $errors = $service->validate(['isActive' => 'yes'], $schema);

    assertTrue(isset($errors['isActive']), 'Expected type error for isActive');
    assertEquals('Expected boolean', $errors['isActive'][0] ?? null);
});

TestSuite::run('validate reports invalid email', function () use ($service): void {
    $schema = [
        'fields' => [
            'email' => ['type' => 'email', 'required' => true],
        ],
    ];

    $errors = $service->validate(['email' => 'invalid_mail'], $schema);

    assertTrue(isset($errors['email']), 'Expected email error');
    assertEquals('Invalid email', $errors['email'][0] ?? null);
});

TestSuite::run('validate checks min and max length for strings', function () use ($service): void {
    $schema = [
        'fields' => [
            'title' => ['type' => 'string', 'required' => true, 'minLength' => 3, 'maxLength' => 5],
        ],
    ];

    $shortErrors = $service->validate(['title' => 'AB'], $schema);
    assertEquals('Minimum length is 3', $shortErrors['title'][0] ?? null);

    $longErrors = $service->validate(['title' => 'ABCDEF'], $schema);
    assertEquals('Maximum length is 5', $longErrors['title'][0] ?? null);
});

TestSuite::run('validate checks min and max range for numbers', function () use ($service): void {
    $schema = [
        'fields' => [
            'qty' => ['type' => 'number', 'required' => true, 'min' => 1, 'max' => 10],
        ],
    ];

    $belowErrors = $service->validate(['qty' => 0], $schema);
    assertEquals('Minimum value is 1', $belowErrors['qty'][0] ?? null);

    $aboveErrors = $service->validate(['qty' => 11], $schema);
    assertEquals('Maximum value is 10', $aboveErrors['qty'][0] ?? null);
});

TestSuite::run('validate checks select options', function () use ($service): void {
    $schema = [
        'fields' => [
            'status' => ['type' => 'select', 'required' => true, 'options' => ['draft', 'published']],
        ],
    ];

    $errors = $service->validate(['status' => 'archived'], $schema);

    assertTrue(isset($errors['status']), 'Expected select option error');
    assertEquals('Value not allowed', $errors['status'][0] ?? null);
});

TestSuite::run('validate supports list style schema field definitions', function () use ($service): void {
    $schema = [
        'fields' => [
            ['name' => 'slug', 'type' => 'string', 'required' => true],
            ['name' => 'priority', 'type' => 'number', 'required' => true],
        ],
    ];

    $errors = $service->validate(['slug' => 'ok', 'priority' => 5], $schema);
    assertEquals([], $errors, 'Expected no errors for list style fields');
});

TestSuite::summary();
exit(TestSuite::exitCode());

