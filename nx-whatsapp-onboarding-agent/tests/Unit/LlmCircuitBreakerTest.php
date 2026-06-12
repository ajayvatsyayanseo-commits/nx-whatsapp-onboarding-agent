<?php

declare(strict_types=1);

namespace NxTutors\WhatsAppOnboarding\Tests\Unit;

use Illuminate\Support\Facades\Cache;
use NxTutors\WhatsAppOnboarding\LLM\LlmCircuitBreaker;
use NxTutors\WhatsAppOnboarding\Tests\TestCase;

final class LlmCircuitBreakerTest extends TestCase
{
    public function testDisabledByDefault(): void
    {
        config()->set('whatsapp_onboarding_cost_limits.llm_enabled', false);

        self::assertFalse((new LlmCircuitBreaker(Cache::store()))->available(1));
    }
}
