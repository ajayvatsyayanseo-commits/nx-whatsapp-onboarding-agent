<?php

declare(strict_types=1);

namespace NxTutors\WhatsAppOnboarding\Conversation\Repositories;

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use NxTutors\WhatsAppOnboarding\Cache\CacheKeyBuilder;
use NxTutors\WhatsAppOnboarding\Profile\Models\OnboardingConversation;

final readonly class RedisStateCache
{
    public function __construct(
        private CacheRepository $cache,
        private CacheKeyBuilder $keys,
    ) {
    }

    public function put(OnboardingConversation $conversation): void
    {
        $ttl = (int) config('whatsapp_onboarding_state_machine.redis_ttl_seconds', 86400);
        $this->cache->put(
            $this->keys->conversationState($conversation->wa_phone),
            [
                'id' => $conversation->id,
                'state' => $conversation->current_state,
                'role' => $conversation->role,
            ],
            $ttl,
        );
    }
}
