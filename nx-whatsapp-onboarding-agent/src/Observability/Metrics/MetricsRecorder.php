<?php

declare(strict_types=1);

namespace NxTutors\WhatsAppOnboarding\Observability\Metrics;

use Illuminate\Contracts\Cache\Repository as CacheRepository;

final readonly class MetricsRecorder
{
    public function __construct(private CacheRepository $cache)
    {
    }

    /** @param array<string, string|int|float|null> $tags */
    public function increment(string $name, array $tags = [], int $by = 1): void
    {
        $key = 'nxtutors:onboarding:metrics:' . $name . ':' . hash('sha256', json_encode($tags));
        $this->cache->increment($key, $by);
        $this->cache->put($key . ':tags', $tags, now()->addDays(8));
    }

    /** @param array<string, string|int|float|null> $tags */
    public function timing(string $name, int $milliseconds, array $tags = []): void
    {
        $this->cache->put('nxtutors:onboarding:metrics:' . $name . ':last', [
            'value' => $milliseconds,
            'tags' => $tags,
            'at' => now()->toIso8601String(),
        ], now()->addDays(8));
    }
}
