<?php

declare(strict_types=1);

namespace NxTutors\WhatsAppOnboarding\Tests\Feature;

use NxTutors\WhatsAppOnboarding\Profile\Services\ProfileFeatureFlagService;
use NxTutors\WhatsAppOnboarding\Security\Guards\PolicyGuardService;
use NxTutors\WhatsAppOnboarding\Tests\TestCase;
use RuntimeException;

final class FeatureFlagPolicyTest extends TestCase
{
    public function testSignupCanBeDisabledByFeatureFlag(): void
    {
        config()->set('whatsapp_onboarding.profile.signup_enabled', false);

        self::assertFalse((new ProfileFeatureFlagService())->signupEnabled());
    }

    public function testPolicyBlocksWhenSignupDisabled(): void
    {
        config()->set('whatsapp_onboarding.profile.signup_enabled', false);

        $this->expectException(RuntimeException::class);
        app(PolicyGuardService::class)->assertCanStart('signup');
    }

    public function testPolicyAllowsStopWhenSignupDisabled(): void
    {
        config()->set('whatsapp_onboarding.profile.signup_enabled', false);

        app(PolicyGuardService::class)->assertCanStart('STOP');
        self::assertTrue(true);
    }
}
