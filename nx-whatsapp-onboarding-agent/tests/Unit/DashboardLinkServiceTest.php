<?php

declare(strict_types=1);

namespace NxTutors\WhatsAppOnboarding\Tests\Unit;

use NxTutors\WhatsAppOnboarding\Profile\Models\Register;
use NxTutors\WhatsAppOnboarding\Profile\Services\DashboardLinkService;
use NxTutors\WhatsAppOnboarding\Security\Passwords\SignedLoginTokenService;
use NxTutors\WhatsAppOnboarding\Tests\TestCase;

final class DashboardLinkServiceTest extends TestCase
{
    public function testBuildsSignedMagicLoginUrlWithoutPhoneOrPassword(): void
    {
        config()->set('whatsapp_onboarding.dashboard.magic_login_enabled', true);
        config()->set('whatsapp_onboarding.dashboard.student_url', 'https://example.test/student');

        $register = new Register();
        $register->forceFill(['user_id' => 'NXS-2026-ABC123', 'phone' => '+919999999999']);

        $url = (new DashboardLinkService(new SignedLoginTokenService()))->dashboardForRole('student', $register);

        self::assertStringContainsString('login_token=', $url);
        self::assertStringNotContainsString('+919999999999', $url);
    }
}
