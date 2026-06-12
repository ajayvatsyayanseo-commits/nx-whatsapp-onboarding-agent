<?php

declare(strict_types=1);

namespace NxTutors\WhatsAppOnboarding\Cache;

use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Cache;

final class RedisClientFactory
{
    public function cache(): Repository
    {
        return Cache::store((string) config('whatsapp_onboarding.redis_connection', 'redis'));
    }
}
