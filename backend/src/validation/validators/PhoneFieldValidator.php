<?php

declare(strict_types=1);

namespace Xestify\validation\validators;

use Xestify\validation\contracts\FieldValidatorInterface;
use Xestify\validation\model\ValidationError;

/**
 * Generic international phone format: digits, spaces, '+', '-' and
 * parentheses only, with a plausible digit count (6-15, matching the ITU-T
 * E.164 maximum). Does not enforce a specific country prefix or layout.
 */
final class PhoneFieldValidator implements FieldValidatorInterface
{
    private const ALLOWED_CHARS_PATTERN = '/^[+\d\s\-()]+$/';
    private const MIN_DIGITS = 6;
    private const MAX_DIGITS = 15;

    public function validate(string $fieldName, mixed $value, array $rules): array
    {
        if (is_string($value) && $this->isValidPhone($value)) {
            return [];
        }

        return [new ValidationError($fieldName, 'invalid_phone', 'Invalid phone number')];
    }

    private function isValidPhone(string $value): bool
    {
        if (preg_match(self::ALLOWED_CHARS_PATTERN, $value) !== 1) {
            return false;
        }

        $digitCount = strlen((string) preg_replace('/\D/', '', $value));

        return $digitCount >= self::MIN_DIGITS && $digitCount <= self::MAX_DIGITS;
    }
}
