<?php

declare(strict_types=1);

namespace Xestify\validation\validators;

use DateTimeImmutable;
use Xestify\validation\contracts\FieldValidatorInterface;
use Xestify\validation\model\ValidationError;

final class DateFieldValidator implements FieldValidatorInterface
{
    public function validate(string $fieldName, mixed $value, array $rules): array
    {
        if (is_string($value) && $this->isValidDate($value)) {
            return [];
        }

        return [new ValidationError($fieldName, 'invalid_type', 'Expected date in YYYY-MM-DD format')];
    }

    private function isValidDate(string $value): bool
    {
        $date = DateTimeImmutable::createFromFormat('Y-m-d', $value);
        if ($date === false) {
            return false;
        }

        return $date->format('Y-m-d') === $value;
    }
}
