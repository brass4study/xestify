<?php

declare(strict_types=1);

namespace Xestify\validation\validators;

use Xestify\validation\contracts\FieldValidatorInterface;
use Xestify\validation\model\ValidationError;

final class EmailFieldValidator implements FieldValidatorInterface
{
    public function validate(string $fieldName, mixed $value, array $rules): array
    {
        if (is_string($value) && filter_var($value, FILTER_VALIDATE_EMAIL) !== false) {
            return [];
        }

        return [new ValidationError($fieldName, 'invalid_email', 'Invalid email')];
    }
}
