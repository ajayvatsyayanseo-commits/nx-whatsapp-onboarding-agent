<?php

declare(strict_types=1);

namespace NxTutors\WhatsAppOnboarding\Profile\Services;

final readonly class ProfileReadinessResult
{
    /** @param list<string> $missingFields */
    private function __construct(
        public bool $valid,
        public array $missingFields = [],
        public ?string $duplicateField = null,
    ) {
    }

    public static function ok(): self
    {
        return new self(true);
    }

    /** @param list<string> $fields */
    public static function missing(array $fields): self
    {
        return new self(false, missingFields: $fields);
    }

    public static function duplicate(string $field): self
    {
        return new self(false, duplicateField: $field);
    }
}
