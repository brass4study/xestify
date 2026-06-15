<?php

declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__, 2));

require_once BASE_PATH . '/tests/unit/helpers.php';
require_once BASE_PATH . '/tests/unit/validation_bootstrap.php';

use Xestify\validation\validators\BooleanFieldValidator;
use Xestify\validation\validators\DateFieldValidator;
use Xestify\validation\validators\EmailFieldValidator;
use Xestify\validation\validators\NumberFieldValidator;
use Xestify\validation\validators\SelectFieldValidator;
use Xestify\validation\validators\StringFieldValidator;
use Xestify\validation\validators\TextFieldValidator;
use Xestify\validation\validators\TimestampFieldValidator;

TestSuite::run('StringFieldValidator accepts strings and rejects other values', function (): void {
    $validator = new StringFieldValidator();

    assertEquals([], $validator->validate('name', 'Alice', []));
    assertEquals('invalid_type', $validator->validate('name', 12, [])[0]->code());
});

TestSuite::run('TextFieldValidator accepts strings and rejects other values', function (): void {
    $validator = new TextFieldValidator();

    assertEquals([], $validator->validate('description', 'Long text', []));
    assertEquals('invalid_type', $validator->validate('description', ['text'], [])[0]->code());
});

TestSuite::run('NumberFieldValidator accepts numeric values and rejects non numeric values', function (): void {
    $validator = new NumberFieldValidator();

    assertEquals([], $validator->validate('age', 42, []));
    assertEquals([], $validator->validate('age', '42.5', []));
    assertEquals('invalid_type', $validator->validate('age', 'old', [])[0]->code());
});

TestSuite::run('BooleanFieldValidator accepts booleans and rejects strings', function (): void {
    $validator = new BooleanFieldValidator();

    assertEquals([], $validator->validate('is_active', true, []));
    assertEquals('invalid_type', $validator->validate('is_active', 'true', [])[0]->code());
});

TestSuite::run('DateFieldValidator validates YYYY-MM-DD dates', function (): void {
    $validator = new DateFieldValidator();

    assertEquals([], $validator->validate('birth_date', '2026-05-11', []));
    assertEquals('invalid_type', $validator->validate('birth_date', '11/05/2026', [])[0]->code());
});

TestSuite::run('TimestampFieldValidator accepts string-compatible timestamps', function (): void {
    $validator = new TimestampFieldValidator();

    assertEquals([], $validator->validate('creation_stamp', 'now', []));
    assertEquals([], $validator->validate('creation_stamp', '2026-05-11T12:00:00Z', []));
    assertEquals('invalid_type', $validator->validate('creation_stamp', 123, [])[0]->code());
});

TestSuite::run('EmailFieldValidator validates email format', function (): void {
    $validator = new EmailFieldValidator();

    assertEquals([], $validator->validate('email', 'ana@example.com', []));
    assertEquals('invalid_email', $validator->validate('email', 'invalid_mail', [])[0]->code());
});

TestSuite::run('SelectFieldValidator validates scalar value and allowed options', function (): void {
    $validator = new SelectFieldValidator();

    $rules = ['options' => ['draft', 'published']];
    assertEquals([], $validator->validate('status', 'draft', $rules));
    assertEquals('invalid_option', $validator->validate('status', 'archived', $rules)[0]->code());
    assertEquals('invalid_type', $validator->validate('status', ['draft'], $rules)[0]->code());
});

TestSuite::summary();
exit(TestSuite::exitCode());
