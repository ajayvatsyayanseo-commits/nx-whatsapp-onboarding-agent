<?php

declare(strict_types=1);

namespace NxTutors\WhatsAppOnboarding\WhatsApp\Services;

use Illuminate\Contracts\Cache\Repository as CacheRepository;

final readonly class WhatsAppOptOutService
{
    public function __construct(private CacheRepository $cache)
    {
    }

    public function isStopCommand(?string $text): bool
    {
        return in_array(mb_strtolower(trim((string) $text)), ['stop', 'unsubscribe'], true);
    }

    public function optOut(string $phone): void
    {
        $this->cache->forever($this->key($phone), true);
    }

    public function isOptedOut(string $phone): bool
    {
        return (bool) $this->cache->get($this->key($phone), false);
    }

    private function key(string $phone): string
    {
        return 'nxtutors:onboarding:optout:' . hash('sha256', $phone);
    }
}
