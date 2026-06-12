<?php

declare(strict_types=1);

namespace NxTutors\WhatsAppOnboarding\Cache;

final class CacheTtlPolicy
{
    public function conversationSeconds(): int
    {
        return (int) config('whatsapp_onboarding_state_machine.redis_ttl_seconds', 86400);
    }
}
