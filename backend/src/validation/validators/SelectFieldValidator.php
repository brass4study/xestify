<?php

declare(strict_types=1);

namespace Xestify\validation\validators;

use Xestify\validation\contracts\FieldValidatorInterface;
use Xestify\validation\model\ValidationError;

final class SelectFieldValidator implements FieldValidatorInterface
{
    public function validate(string $fieldName, mixed $value, array $rules): array
    {
        if (!is_scalar($value)) {
            return [new ValidationError($fieldName, 'invalid_type', 'Expected scalar value for select')];
        }

        if ($this->isAllowedValue($value, $rules['options'] ?? [])) {
            return [];
        }

        return [new ValidationError($fieldName, 'invalid_option', 'Value not allowed')];
    }

    private function isAllowedValue(mixed $value, mixed $options): bool
    {
        if (!is_array($options) || $options === []) {
            return true;
        }

        foreach ($options as $option) {
            $optionValue = is_array($option) ? ($option['value'] ?? null) : $option;
            if ($optionValue !== null && (string) $optionValue === (string) $value) {
                return true;
            }
        }

        return false;
    }
}
