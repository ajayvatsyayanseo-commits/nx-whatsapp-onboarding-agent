<?php

declare(strict_types=1);

namespace NxTutors\WhatsAppOnboarding\Common\DTO;

final readonly class CollectedField
{
    public function __construct(
        public string $name,
        public string $value,
    ) {
    }
}
