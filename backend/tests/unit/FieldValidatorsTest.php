<?php

declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__, 2));

require_once BASE_PATH . '/tests/unit/helpers.php';
require_once BASE_PATH . '/tests/unit/validation_bootstrap.php';

use Xestify\validation\validators\BooleanFieldValidator;
use Xestify\validation\validators\DateFieldValidator;
use Xestify\validation\validators\MailFieldValidator;
use Xestify\validation\validators\NumberFieldValidator;
use Xestify\validation\validators\PhoneFieldValidator;
use Xestify\validation\validators\SelectFieldValidator;
use Xestify\validation\validators\StringFieldValidator;
use Xestify\validation\validators\TimestampFieldValidator;
use Xestify\validation\validators\UuidFieldValidator;

TestSuite::run('StringFieldValidator accepts strings and rejects other values', function (): void {
    $validator = new StringFieldValidator();

    assertEquals([], $validator->validate('name', 'Alice', []));
    assertEquals('invalid_type', $validator->validate('name', 12, [])[0]->code());
    assertEquals('Expected string', $validator->validate('name', 12, [])[0]->message());
});

TestSuite::run('StringFieldValidator registered under "text" accepts strings and uses the text message', function (): void {
    $validator = new StringFieldValidator('text');

    assertEquals([], $validator->validate('description', 'Long text', []));
    assertEquals('invalid_type', $validator->validate('description', ['text'], [])[0]->code());
    assertEquals('Expected text', $validator->validate('description', ['text'], [])[0]->message());
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

TestSuite::run('TimestampFieldValidator accepts real timestamps and the "now" schema default', function (): void {
    $validator = new TimestampFieldValidator();

    assertEquals([], $validator->validate('creation_stamp', 'now', []));
    assertEquals([], $validator->validate('creation_stamp', '2026-05-11T12:00:00Z', []));
    assertEquals('invalid_type', $validator->validate('creation_stamp', 123, [])[0]->code());
});

TestSuite::run('TimestampFieldValidator rejects strings that are not a real timestamp', function (): void {
    $validator = new TimestampFieldValidator();

    assertEquals('invalid_type', $validator->validate('creation_stamp', 'not-a-real-timestamp', [])[0]->code());
    assertEquals('invalid_type', $validator->validate('creation_stamp', '', [])[0]->code());
});

TestSuite::run('MailFieldValidator validates email format', function (): void {
    $validator = new MailFieldValidator();

    assertEquals([], $validator->validate('email', 'ana@example.com', []));
    assertEquals('invalid_mail', $validator->validate('email', 'invalid_mail', [])[0]->code());
});

TestSuite::run('PhoneFieldValidator accepts generic international formats', function (): void {
    $validator = new PhoneFieldValidator();

    assertEquals([], $validator->validate('phone', '612345678', []));
    assertEquals([], $validator->validate('phone', '+34 612 345 678', []));
    assertEquals([], $validator->validate('phone', '(034) 612-345-678', []));
});

TestSuite::run('PhoneFieldValidator rejects invalid characters and implausible digit counts', function (): void {
    $validator = new PhoneFieldValidator();

    assertEquals('invalid_phone', $validator->validate('phone', 'not-a-phone', [])[0]->code());
    assertEquals('invalid_phone', $validator->validate('phone', '123', [])[0]->code());
    assertEquals('invalid_phone', $validator->validate('phone', str_repeat('1', 16), [])[0]->code());
});

TestSuite::run('UuidFieldValidator validates UUID v4 format and rejects malformed values', function (): void {
    $validator = new UuidFieldValidator();

    assertEquals([], $validator->validate('id', '4c2b6b0a-9a3f-4f3e-8c1e-2a7e6a3b9d10', []));
    assertEquals([], $validator->validate('id', '4C2B6B0A-9A3F-4F3E-8C1E-2A7E6A3B9D10', []));
    assertEquals('invalid_type', $validator->validate('id', 'not-a-uuid', [])[0]->code());
    assertEquals('invalid_type', $validator->validate('id', 123, [])[0]->code());
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
