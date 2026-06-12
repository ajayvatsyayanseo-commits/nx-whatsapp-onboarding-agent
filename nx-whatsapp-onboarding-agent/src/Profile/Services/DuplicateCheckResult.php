<?php

declare(strict_types=1);

namespace NxTutors\WhatsAppOnboarding\Profile\Services;

final readonly class DuplicateCheckResult
{
    private function __construct(public bool $valid, public ?string $field = null)
    {
    }

    public static function ok(): self
    {
        return new self(true);
    }

    public static function conflict(string $field): self
    {
        return new self(false, $field);
    }
}
