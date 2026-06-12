<?php

declare(strict_types=1);

namespace NxTutors\WhatsAppOnboarding\Tests\Unit;

use Illuminate\Support\Facades\Cache;
use NxTutors\WhatsAppOnboarding\Tests\TestCase;
use NxTutors\WhatsAppOnboarding\WhatsApp\Services\WhatsAppRateLimiter;
use RuntimeException;

final class WhatsAppRateLimiterTest extends TestCase
{
    public function testBlocksAfterConfiguredPhoneLimit(): void
    {
        config()->set('whatsapp_onboarding_cost_limits.max_outbound_messages_per_phone_per_hour', 1);
        $limiter = new WhatsAppRateLimiter(Cache::store());

        $limiter->assertCanSend('+919999999999');
        $this->expectException(RuntimeException::class);
        $limiter->assertCanSend('+919999999999');
    }
}
