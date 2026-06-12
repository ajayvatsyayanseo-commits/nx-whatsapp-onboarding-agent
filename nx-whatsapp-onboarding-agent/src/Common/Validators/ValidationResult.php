<?php

declare(strict_types=1);

namespace NxTutors\WhatsAppOnboarding\Common\Validators;

final readonly class ValidationResult
{
    /** @param list<string> $errors */
    private function __construct(public bool $valid, public array $errors = [])
    {
    }

    public static function ok(): self
    {
        return new self(true);
    }

    /** @param list<string> $errors */
    public static function fail(array $errors): self
    {
        return new self(false, $errors);
    }
}
