<?php

declare(strict_types=1);

namespace NxTutors\WhatsAppOnboarding\Cache;

final class CacheKeyBuilder
{
    public function conversationState(string $waPhone): string
    {
        return 'nxtutors:onboarding:conversation:' . hash('sha256', $waPhone);
    }
}
