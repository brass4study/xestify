<?php

declare(strict_types=1);

namespace Xestify\services;

use Xestify\validation\DefaultFieldValidatorRegistryFactory;
use Xestify\validation\FieldValidatorRegistry;
use Xestify\validation\model\ValidationError;
use Xestify\validation\model\ValidationResult;
use Xestify\validation\schema\SchemaFieldExtractor;
use Xestify\validation\support\ValidationRules;

final class ValidationService
{
    public function __construct(
        private ?SchemaFieldExtractor $fieldExtractor = null,
        private ?FieldValidatorRegistry $validatorRegistry = null,
        private ?ValidationRules $rules = null
    ) {
        $this->fieldExtractor ??= new SchemaFieldExtractor();
        $this->validatorRegistry ??= (new DefaultFieldValidatorRegistryFactory())->create();
        $this->rules ??= new ValidationRules();
    }

    public function validate(array $data, array $schema, bool $requireAll = true): ValidationResult
    {
        $errors = [];

        foreach ($this->fieldExtractor->extract($schema) as $fieldName => $fieldRules) {
            $errors = array_merge($errors, $this->validateField($fieldName, $data, $fieldRules, $requireAll));
        }

        return $errors === []
            ? ValidationResult::valid()
            : ValidationResult::fromErrors($errors);
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed> $fieldRules
     * @return list<ValidationError>
     */
    private function validateField(string $fieldName, array $data, array $fieldRules, bool $requireAll): array
    {
        $isPresent = array_key_exists($fieldName, $data);
        $value = $isPresent ? $data[$fieldName] : null;

        if ($requireAll && $this->rules->isRequired($fieldRules) && $this->rules->isMissing($isPresent, $value)) {
            return [new ValidationError($fieldName, 'required', 'Field is required')];
        }

        if (!$this->shouldValidateValue($isPresent, $value)) {
            return [];
        }

        return $this->validatePresentValue($fieldName, $value, $fieldRules);
    }

    private function shouldValidateValue(bool $isPresent, mixed $value): bool
    {
        return $isPresent && $value !== null && $value !== '';
    }

    /**
     * @param array<string, mixed> $fieldRules
     * @return list<ValidationError>
     */
    private function validatePresentValue(string $fieldName, mixed $value, array $fieldRules): array
    {
        $type = is_string($fieldRules['type'] ?? null) ? $fieldRules['type'] : 'string';
        $validator = $this->validatorRegistry->get($type);

        if ($validator === null) {
            return [new ValidationError($fieldName, 'unknown_type', 'Unsupported type: ' . $type)];
        }

        $errors = $validator->validate($fieldName, $value, $fieldRules);
        if ($errors !== []) {
            return $errors;
        }

        return $this->validateCommonRules($fieldName, $value, $fieldRules, $type);
    }

    /**
     * @param array<string, mixed> $fieldRules
     * @return list<ValidationError>
     */
    private function validateCommonRules(string $fieldName, mixed $value, array $fieldRules, string $type): array
    {
        if ($type === 'number') {
            return $this->rules->validateNumericBounds($fieldName, (float) $value, $fieldRules);
        }

        if ($this->usesStringBounds($type)) {
            return $this->rules->validateStringBounds($fieldName, (string) $value, $fieldRules);
        }

        return [];
    }

    private function usesStringBounds(string $type): bool
    {
        return in_array($type, ['string', 'text', 'email', 'date', 'timestamp', 'select'], true);
    }
}
