<?php

declare(strict_types=1);

namespace Xestify\validation;

use Xestify\validation\validators\BooleanFieldValidator;
use Xestify\validation\validators\DateFieldValidator;
use Xestify\validation\validators\MailFieldValidator;
use Xestify\validation\validators\NumberFieldValidator;
use Xestify\validation\validators\PhoneFieldValidator;
use Xestify\validation\validators\SelectFieldValidator;
use Xestify\validation\validators\StringFieldValidator;
use Xestify\validation\validators\TimestampFieldValidator;
use Xestify\validation\validators\UuidFieldValidator;

final class DefaultFieldValidatorRegistryFactory
{
    public function create(): FieldValidatorRegistry
    {
        return new FieldValidatorRegistry([
            'string' => new StringFieldValidator(),
            'text' => new StringFieldValidator('text'),
            'number' => new NumberFieldValidator(),
            'boolean' => new BooleanFieldValidator(),
            'date' => new DateFieldValidator(),
            'timestamp' => new TimestampFieldValidator(),
            'mail' => new MailFieldValidator(),
            'phone' => new PhoneFieldValidator(),
            'select' => new SelectFieldValidator(),
            'uuid' => new UuidFieldValidator(),
        ]);
    }
}
