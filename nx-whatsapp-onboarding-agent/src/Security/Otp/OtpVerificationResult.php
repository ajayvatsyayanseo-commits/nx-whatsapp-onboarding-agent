<?php

declare(strict_types=1);

namespace NxTutors\WhatsAppOnboarding\Security\Otp;

final readonly class OtpVerificationResult
{
    private function __construct(
        public bool $valid,
        public bool $expired = false,
        public bool $tooManyAttempts = false,
    ) {
    }

    public static function valid(): self
    {
        return new self(true);
    }

    public static function invalid(): self
    {
        return new self(false);
    }

    public static function expired(): self
    {
        return new self(false, expired: true);
    }

    public static function tooManyAttempts(): self
    {
        return new self(false, tooManyAttempts: true);
    }
}
