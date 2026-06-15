<?php

declare(strict_types=1);

namespace Xestify\validation\validators;

use Xestify\validation\contracts\FieldValidatorInterface;
use Xestify\validation\model\ValidationError;

final class TextFieldValidator implements FieldValidatorInterface
{
    public function validate(string $fieldName, mixed $value, array $rules): array
    {
        if (is_string($value)) {
            return [];
        }

        return [new ValidationError($fieldName, 'invalid_type', 'Expected text')];
    }
}
