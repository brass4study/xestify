<?php

declare(strict_types=1);

namespace Xestify\validation;

use Xestify\validation\contracts\FieldValidatorInterface;

final class FieldValidatorRegistry
{
    /** @var array<string, FieldValidatorInterface> */
    private array $validators;

    /** @param array<string, FieldValidatorInterface> $validators */
    public function __construct(array $validators)
    {
        $this->validators = $validators;
    }

    public function get(string $type): ?FieldValidatorInterface
    {
        return $this->validators[$type] ?? null;
    }
}
