<?php

declare(strict_types=1);

namespace NxTutors\WhatsAppOnboarding\WhatsApp\Services;

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use RuntimeException;

final readonly class WhatsAppRateLimiter
{
    public function __construct(private CacheRepository $cache)
    {
    }

    public function assertCanSend(string $phone): void
    {
        $limit = (int) config('whatsapp_onboarding_cost_limits.max_outbound_messages_per_phone_per_hour', 20);
        $globalLimit = (int) config('whatsapp_onboarding_cost_limits.max_outbound_messages_global_per_minute', 1000);
        $key = 'nxtutors:onboarding:rate:' . hash('sha256', $phone);
        $globalKey = 'nxtutors:onboarding:rate:global:' . now()->format('YmdHi');
        $count = (int) $this->cache->get($key, 0);
        $globalCount = (int) $this->cache->get($globalKey, 0);

        if ($count >= $limit) {
            throw new RuntimeException('WhatsApp outbound rate limit exceeded.');
        }

        if ($globalCount >= $globalLimit) {
            throw new RuntimeException('WhatsApp global provider rate limit exceeded.');
        }

        $this->cache->put($key, $count + 1, now()->addHour());
        $this->cache->put($globalKey, $globalCount + 1, now()->addMinutes(2));
    }
}
