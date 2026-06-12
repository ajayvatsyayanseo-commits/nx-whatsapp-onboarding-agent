<?php

declare(strict_types=1);

namespace NxTutors\WhatsAppOnboarding\Profile\DTO;

use NxTutors\WhatsAppOnboarding\Profile\Models\Register;

final readonly class ProfileWriteResult
{
    public function __construct(
        public Register $register,
        public string $temporaryPassword,
    ) {
    }
}
