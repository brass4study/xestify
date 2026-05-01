<?php

declare(strict_types=1);

namespace Xestify\exceptions;

use RuntimeException;

/**
 * Thrown when entity data fails schema validation.
 * Carries the full errors map returned by ValidationService::validate().
 */
class ValidationException extends RuntimeException
{
    /** @var array<string, list<string>> */
    private array $errors;

    /** @param array<string, list<string>> $errors */
    public function __construct(array $errors)
    {
        parent::__construct('Validation failed');
        $this->errors = $errors;
    }

    /** @return array<string, list<string>> */
    public function getErrors(): array
    {
        return $this->errors;
    }
}
