<?php

declare(strict_types=1);

namespace NxTutors\WhatsAppOnboarding\Tests\Unit;

use Illuminate\Support\Facades\Cache;
use NxTutors\WhatsAppOnboarding\Tests\TestCase;
use NxTutors\WhatsAppOnboarding\WhatsApp\Services\MetaCircuitBreaker;
use RuntimeException;

final class MetaCircuitBreakerTest extends TestCase
{
    public function testOpensAfterFailureThreshold(): void
    {
        config()->set('whatsapp_onboarding_cost_limits.meta_circuit_breaker.failure_threshold', 1);
        $breaker = new MetaCircuitBreaker(Cache::store());

        $breaker->recordFailure(429);
        $this->expectException(RuntimeException::class);
        $breaker->assertAvailable();
    }
}
