<?php

declare(strict_types=1);

namespace NxTutors\WhatsAppOnboarding\WhatsApp\DTO;

final readonly class InboundWhatsAppMessage
{
    /** @param array<string, mixed> $raw */
    public function __construct(
        public string $messageId,
        public string $fromPhone,
        public ?string $text,
        public string $type,
        public array $raw,
        public ?string $receivedAt = null,
    ) {
    }
}
