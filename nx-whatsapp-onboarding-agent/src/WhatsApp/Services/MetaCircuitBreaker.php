<?php

declare(strict_types=1);

namespace NxTutors\WhatsAppOnboarding\WhatsApp\Services;

use Illuminate\Contracts\Cache\Repository as CacheRepository;

final readonly class MetaCircuitBreaker
{
    public function __construct(private CacheRepository $cache)
    {
    }

    public function assertAvailable(): void
    {
        $until = (int) $this->cache->get('nxtutors:onboarding:meta:circuit_open_until', 0);
        if ($until > time()) {
            throw new \RuntimeException('Meta provider circuit breaker is open.');
        }
    }

    public function recordSuccess(): void
    {
        $this->cache->forget('nxtutors:onboarding:meta:failures');
    }

    public function recordFailure(?int $statusCode = null): void
    {
        if ($statusCode !== null && ! in_array($statusCode, [429, 500, 502, 503, 504], true)) {
            return;
        }

        $key = 'nxtutors:onboarding:meta:failures';
        $failures = (int) $this->cache->get($key, 0) + 1;
        $this->cache->put($key, $failures, now()->addMinutes(10));

        if ($failures >= (int) config('whatsapp_onboarding_cost_limits.meta_circuit_breaker.failure_threshold', 5)) {
            $cooldown = (int) config('whatsapp_onboarding_cost_limits.meta_circuit_breaker.cooldown_seconds', 60);
            $this->cache->put('nxtutors:onboarding:meta:circuit_open_until', time() + $cooldown, now()->addSeconds($cooldown));
        }
    }
}
