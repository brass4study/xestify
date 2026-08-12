<?php

declare(strict_types=1);

namespace Xestify\validation\validators;

use DateTimeImmutable;
use Exception;
use Xestify\validation\contracts\FieldValidatorInterface;
use Xestify\validation\model\ValidationError;

final class TimestampFieldValidator implements FieldValidatorInterface
{
    public function validate(string $fieldName, mixed $value, array $rules): array
    {
        if (is_string($value) && $this->isValidTimestamp($value)) {
            return [];
        }

        return [new ValidationError($fieldName, 'invalid_type', 'Expected timestamp')];
    }

    /**
     * Accepts absolute timestamps (ISO-8601 and other formats PHP's date
     * parser recognizes) as well as the 'now' keyword used as schema default
     * for auto-generated fields (e.g. plugins/clients/schema.json). Rejects
     * arbitrary non-date strings, which the old is_string()-only check let through.
     */
    private function isValidTimestamp(string $value): bool
    {
        if (trim($value) === '') {
            return false;
        }

        try {
            $parsed = new DateTimeImmutable($value);
            return $parsed instanceof DateTimeImmutable;
        } catch (Exception) {
            return false;
        }
    }
}
