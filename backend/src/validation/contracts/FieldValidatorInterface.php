<?php

declare(strict_types=1);

namespace Xestify\validation\contracts;

interface FieldValidatorInterface
{
    /**
     * @param array<string, mixed> $rules
     * @return list<\Xestify\validation\model\ValidationError>
     */
    public function validate(string $fieldName, mixed $value, array $rules): array;
}
