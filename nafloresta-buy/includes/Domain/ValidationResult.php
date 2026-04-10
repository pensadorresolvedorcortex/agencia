<?php

namespace NaFlorestaBuy\Domain;

class ValidationResult
{
    private array $errors = [];

    public function addError(string $code, string $message): void
    {
        $this->errors[] = ['code' => $code, 'message' => $message];
    }

    public function hasErrors(): bool
    {
        return !empty($this->errors);
    }

    public function errors(): array
    {
        return $this->errors;
    }
}
