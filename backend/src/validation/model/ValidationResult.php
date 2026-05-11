<?php

declare(strict_types=1);

namespace Xestify\validation\model;

final class ValidationResult
{
    /** @param list<ValidationError> $errors */
    private function __construct(private array $errors)
    {
    }

    public static function valid(): self
    {
        return new self([]);
    }

    /** @param list<ValidationError> $errors */
    public static function fromErrors(array $errors): self
    {
        return new self($errors);
    }

    public function isValid(): bool
    {
        return $this->errors === [];
    }

    /**
     * @return list<array{field: string, code: string, message: string}>
     */
    public function errors(): array
    {
        return array_map(
            static fn(ValidationError $error): array => $error->toArray(),
            $this->errors
        );
    }

    /**
     * @return array{valid: bool, errors: list<array{field: string, code: string, message: string}>}
     */
    public function toArray(): array
    {
        return [
            'valid' => $this->isValid(),
            'errors' => $this->errors(),
        ];
    }
}
