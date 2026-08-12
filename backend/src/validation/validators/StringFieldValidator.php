<?php

declare(strict_types=1);

namespace Xestify\validation\validators;

use Xestify\validation\contracts\FieldValidatorInterface;
use Xestify\validation\model\ValidationError;

/**
 * Validates that a value is a string. Also registered under the 'text' type
 * key (DefaultFieldValidatorRegistryFactory) via the $expectedLabel
 * constructor argument, since 'string' and 'text' fields share the exact
 * same validation rule and only differ in their error message wording.
 */
final class StringFieldValidator implements FieldValidatorInterface
{
    public function __construct(private string $expectedLabel = 'string')
    {
    }

    public function validate(string $fieldName, mixed $value, array $rules): array
    {
        if (is_string($value)) {
            return [];
        }

        return [new ValidationError($fieldName, 'invalid_type', 'Expected ' . $this->expectedLabel)];
    }
}
