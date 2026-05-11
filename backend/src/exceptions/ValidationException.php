<?php

declare(strict_types=1);

namespace Xestify\exceptions;

use RuntimeException;

/**
 * Thrown when entity data fails schema validation.
 * Carries the structured errors returned by ValidationResult::errors().
 */
class ValidationException extends RuntimeException
{
    /** @var list<array{field: string, code: string, message: string}> */
    private array $errors;

    /** @param list<array{field: string, code: string, message: string}> $errors */
    public function __construct(array $errors)
    {
        parent::__construct('Validation failed');
        $this->errors = $errors;
    }

    /** @return list<array{field: string, code: string, message: string}> */
    public function getErrors(): array
    {
        return $this->errors;
    }
}
