<?php

declare(strict_types=1);

namespace NxTutors\WhatsAppOnboarding\Profile\DTO;

final readonly class ProfileCreationCommand
{
    public function __construct(
        public int $conversationId,
        public string $role,
    ) {
    }
}
