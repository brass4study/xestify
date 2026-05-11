<?php

declare(strict_types=1);

namespace Xestify\validation\model;

final class ValidationError
{
    public function __construct(
        private string $field,
        private string $code,
        private string $message
    ) {
    }

    public function field(): string
    {
        return $this->field;
    }

    public function code(): string
    {
        return $this->code;
    }

    public function message(): string
    {
        return $this->message;
    }

    /**
     * @return array{field: string, code: string, message: string}
     */
    public function toArray(): array
    {
        return [
            'field' => $this->field,
            'code' => $this->code,
            'message' => $this->message,
        ];
    }
}
