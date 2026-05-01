<?php

declare(strict_types=1);

namespace Xestify\services;

final class ValidationService
{
    public function validate(array $data, array $schema): array
    {
        $errors = [];
        $fields = $this->extractFields($schema);

        foreach ($fields as $fieldName => $rules) {
            $fieldErrors = $this->validateField($fieldName, $data, $rules);
            if ($fieldErrors !== []) {
                $errors[$fieldName] = $fieldErrors;
            }
        }

        return $errors;
    }

    private function extractFields(array $schema): array
    {
        if (!isset($schema['fields']) || !is_array($schema['fields'])) {
            return [];
        }

        $fields = [];
        foreach ($schema['fields'] as $key => $definition) {
            if (is_string($key) && is_array($definition)) {
                $fields[$key] = $definition;
                continue;
            }

            if (!is_array($definition)) {
                continue;
            }

            $name = $this->resolveFieldName($definition);
            if ($name === null) {
                continue;
            }

            $fields[$name] = $definition;
        }

        return $fields;
    }

    private function resolveFieldName(array $definition): ?string
    {
        foreach (['name', 'slug', 'key'] as $candidate) {
            if (isset($definition[$candidate]) && is_string($definition[$candidate])) {
                $value = trim($definition[$candidate]);
                if ($value !== '') {
                    return $value;
                }
            }
        }

        return null;
    }

    private function validateField(string $fieldName, array $data, array $rules): array
    {
        $errors = [];
        $isRequired = $this->toBool($rules['required'] ?? false);
        $isPresent = array_key_exists($fieldName, $data);
        $value = $isPresent ? $data[$fieldName] : null;

        if ($isRequired && $this->isMissing($isPresent, $value)) {
            $errors[] = 'Field is required';
        } elseif ($isPresent && $value !== null && $value !== '') {
            $type = (string) ($rules['type'] ?? 'string');
            $errors = $this->validateType($type, $value, $rules);

            if ($errors === [] && $this->usesStringBounds($type)) {
                $errors = array_merge($errors, $this->validateStringBounds((string) $value, $rules));
            }

            if ($errors === [] && $type === 'number') {
                $errors = array_merge($errors, $this->validateNumericBounds((float) $value, $rules));
            }
        }

        return $errors;
    }

    private function validateType(string $type, mixed $value, array $rules): array
    {
        $errors = [];

        switch ($type) {
            case 'string':
                $errors = $this->validateStringType($value);
                break;
            case 'number':
                $errors = $this->validateNumberType($value);
                break;
            case 'boolean':
                $errors = $this->validateBooleanType($value);
                break;
            case 'date':
                $errors = $this->validateDateType($value);
                break;
            case 'email':
                $errors = $this->validateEmailType($value);
                break;
            case 'select':
                $errors = $this->validateSelect($value, $rules);
                break;
            default:
                $errors[] = 'Unsupported type: ' . $type;
                break;
        }

        return $errors;
    }

    private function validateStringType(mixed $value): array
    {
        return is_string($value) ? [] : ['Expected string'];
    }

    private function validateNumberType(mixed $value): array
    {
        $isValid = is_int($value) || is_float($value) || (is_string($value) && is_numeric($value));
        return $isValid ? [] : ['Expected number'];
    }

    private function validateBooleanType(mixed $value): array
    {
        return is_bool($value) ? [] : ['Expected boolean'];
    }

    private function validateDateType(mixed $value): array
    {
        $isValid = is_string($value) && $this->isValidDate($value);
        return $isValid ? [] : ['Expected date in YYYY-MM-DD format'];
    }

    private function validateEmailType(mixed $value): array
    {
        $isValid = is_string($value) && $this->isValidEmail($value);
        return $isValid ? [] : ['Invalid email'];
    }

    private function usesStringBounds(string $type): bool
    {
        return in_array($type, ['string', 'email', 'date', 'select'], true);
    }

    private function validateSelect(mixed $value, array $rules): array
    {
        $errors = [];

        if (!is_scalar($value)) {
            $errors[] = 'Expected scalar value for select';
        } else {
            $options = $rules['options'] ?? [];
            if (is_array($options) && $options !== []) {
                $isAllowed = false;
                foreach ($options as $option) {
                    if ((string) $option === (string) $value) {
                        $isAllowed = true;
                        break;
                    }
                }

                if (!$isAllowed) {
                    $errors[] = 'Value not allowed';
                }
            }
        }

        return $errors;
    }

    private function validateStringBounds(string $value, array $rules): array
    {
        $errors = [];

        if (isset($rules['minLength']) && is_numeric($rules['minLength'])) {
            $minLength = (int) $rules['minLength'];
            if (mb_strlen($value) < $minLength) {
                $errors[] = 'Minimum length is ' . $minLength;
            }
        }

        if (isset($rules['maxLength']) && is_numeric($rules['maxLength'])) {
            $maxLength = (int) $rules['maxLength'];
            if (mb_strlen($value) > $maxLength) {
                $errors[] = 'Maximum length is ' . $maxLength;
            }
        }

        return $errors;
    }

    private function validateNumericBounds(float $value, array $rules): array
    {
        $errors = [];

        if (isset($rules['min']) && is_numeric($rules['min'])) {
            $min = (float) $rules['min'];
            if ($value < $min) {
                $errors[] = 'Minimum value is ' . $this->formatNumber($min);
            }
        }

        if (isset($rules['max']) && is_numeric($rules['max'])) {
            $max = (float) $rules['max'];
            if ($value > $max) {
                $errors[] = 'Maximum value is ' . $this->formatNumber($max);
            }
        }

        return $errors;
    }

    private function formatNumber(float $number): string
    {
        if (floor($number) === $number) {
            return (string) (int) $number;
        }

        return (string) $number;
    }

    private function isMissing(bool $isPresent, mixed $value): bool
    {
        if (!$isPresent) {
            return true;
        }

        if ($value === null) {
            return true;
        }

        return is_string($value) && trim($value) === '';
    }

    private function isValidDate(string $value): bool
    {
        $date = \DateTimeImmutable::createFromFormat('Y-m-d', $value);
        if ($date === false) {
            return false;
        }

        return $date->format('Y-m-d') === $value;
    }

    private function isValidEmail(string $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
    }

    private function toBool(mixed $value): bool
    {
        $result = false;

        if (is_bool($value)) {
            $result = $value;
        } elseif (is_int($value)) {
            $result = $value !== 0;
        } elseif (is_string($value)) {
            $normalized = strtolower(trim($value));
            $result = $normalized === '1' || $normalized === 'true' || $normalized === 'yes';
        }

        return $result;
    }
}

