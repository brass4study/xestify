<?php

declare(strict_types=1);

namespace Xestify\validation\support;

use Xestify\validation\model\ValidationError;

final class ValidationRules
{
    /** @param array<string, mixed> $rules */
    public function isRequired(array $rules): bool
    {
        return $this->toBool($rules['required'] ?? false);
    }

    public function isMissing(bool $isPresent, mixed $value): bool
    {
        if (!$isPresent || $value === null) {
            return true;
        }

        return is_string($value) && trim($value) === '';
    }

    /**
     * @param array<string, mixed> $rules
     * @return list<ValidationError>
     */
    public function validateStringBounds(string $fieldName, string $value, array $rules): array
    {
        $errors = [];
        $minLength = $this->numericRule($rules, 'minLength', 'min_length');
        $maxLength = $this->numericRule($rules, 'maxLength', 'max_length');

        if ($minLength !== null && mb_strlen($value) < (int) $minLength) {
            $errors[] = new ValidationError($fieldName, 'min_length', 'Minimum length is ' . (int) $minLength);
        }

        if ($maxLength !== null && mb_strlen($value) > (int) $maxLength) {
            $errors[] = new ValidationError($fieldName, 'max_length', 'Maximum length is ' . (int) $maxLength);
        }

        return $errors;
    }

    /**
     * @param array<string, mixed> $rules
     * @return list<ValidationError>
     */
    public function validateNumericBounds(string $fieldName, float $value, array $rules): array
    {
        $errors = [];
        $min = $this->numericRule($rules, 'min');
        $max = $this->numericRule($rules, 'max');

        if ($min !== null && $value < $min) {
            $errors[] = new ValidationError($fieldName, 'min_value', 'Minimum value is ' . $this->formatNumber($min));
        }

        if ($max !== null && $value > $max) {
            $errors[] = new ValidationError($fieldName, 'max_value', 'Maximum value is ' . $this->formatNumber($max));
        }

        return $errors;
    }

    /** @param array<string, mixed> $rules */
    private function numericRule(array $rules, string $primary, ?string $secondary = null): ?float
    {
        $value = $rules[$primary] ?? ($secondary !== null ? ($rules[$secondary] ?? null) : null);
        return is_numeric($value) ? (float) $value : null;
    }

    private function formatNumber(float $number): string
    {
        if (floor($number) === $number) {
            return (string) (int) $number;
        }

        return (string) $number;
    }

    private function toBool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value)) {
            return $value !== 0;
        }

        if (!is_string($value)) {
            return false;
        }

        $normalized = strtolower(trim($value));
        return $normalized === '1' || $normalized === 'true' || $normalized === 'yes';
    }
}
