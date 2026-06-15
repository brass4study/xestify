<?php

declare(strict_types=1);

namespace Xestify\validation;

use Xestify\validation\validators\BooleanFieldValidator;
use Xestify\validation\validators\DateFieldValidator;
use Xestify\validation\validators\EmailFieldValidator;
use Xestify\validation\validators\NumberFieldValidator;
use Xestify\validation\validators\SelectFieldValidator;
use Xestify\validation\validators\StringFieldValidator;
use Xestify\validation\validators\TextFieldValidator;
use Xestify\validation\validators\TimestampFieldValidator;

final class DefaultFieldValidatorRegistryFactory
{
    public function create(): FieldValidatorRegistry
    {
        return new FieldValidatorRegistry([
            'string' => new StringFieldValidator(),
            'text' => new TextFieldValidator(),
            'number' => new NumberFieldValidator(),
            'boolean' => new BooleanFieldValidator(),
            'date' => new DateFieldValidator(),
            'timestamp' => new TimestampFieldValidator(),
            'email' => new EmailFieldValidator(),
            'select' => new SelectFieldValidator(),
        ]);
    }
}
