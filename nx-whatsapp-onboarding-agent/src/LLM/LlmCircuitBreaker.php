<?php

declare(strict_types=1);

namespace NxTutors\WhatsAppOnboarding\LLM;

use Illuminate\Contracts\Cache\Repository as CacheRepository;

final readonly class LlmCircuitBreaker
{
    public function __construct(private CacheRepository $cache)
    {
    }

    public function available(int $conversationId): bool
    {
        if (! (bool) config('whatsapp_onboarding_cost_limits.llm_enabled', false)) {
            return false;
        }

        if ((bool) config('whatsapp_onboarding.profile.llm_extraction_disabled', false)) {
            return false;
        }

        if ((int) $this->cache->get('nxtutors:onboarding:llm:circuit_open_until', 0) > time()) {
            return false;
        }

        $calls = (int) $this->cache->get("nxtutors:onboarding:llm:calls:{$conversationId}", 0);

        return $calls < (int) config('whatsapp_onboarding_cost_limits.llm_max_calls_per_conversation', 2);
    }

    public function recordCall(int $conversationId, int $latencyMs, bool $success, int $tokens = 0): void
    {
        $this->cache->increment("nxtutors:onboarding:llm:calls:{$conversationId}");
        $this->cache->increment('nxtutors:onboarding:llm:tokens:' . now()->format('Ymd'), $tokens);

        if (! $success || $latencyMs > (int) config('whatsapp_onboarding_cost_limits.llm_latency_breaker_ms', 2500)) {
            $this->cache->increment('nxtutors:onboarding:llm:failures:' . now()->format('YmdHi'));
        }
    }

    public function open(int $seconds = 300): void
    {
        $this->cache->put('nxtutors:onboarding:llm:circuit_open_until', time() + $seconds, now()->addSeconds($seconds));
    }
}
