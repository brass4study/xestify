<?php

declare(strict_types=1);

namespace Xestify\validation\validators;

use Xestify\validation\contracts\FieldValidatorInterface;
use Xestify\validation\model\ValidationError;

final class UuidFieldValidator implements FieldValidatorInterface
{
    private const UUID_PATTERN = '/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i';

    public function validate(string $fieldName, mixed $value, array $rules): array
    {
        if (is_string($value) && preg_match(self::UUID_PATTERN, $value) === 1) {
            return [];
        }

        return [new ValidationError($fieldName, 'invalid_type', 'Expected a valid UUID')];
    }
}
