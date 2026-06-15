<?php

declare(strict_types=1);

namespace NxTutors\WhatsAppOnboarding\Tests\Unit;

use NxTutors\WhatsAppOnboarding\Profile\Models\Register;
use NxTutors\WhatsAppOnboarding\Profile\Services\LoginMessageBuilder;
use NxTutors\WhatsAppOnboarding\Security\PiiMasking\PiiMasker;
use NxTutors\WhatsAppOnboarding\Tests\TestCase;

final class LoginMessageBuilderEmailModeTest extends TestCase
{
    public function testEmailModeDoesNotSayLoginPhone(): void
    {
        config()->set('whatsapp_onboarding.nxtutors_legacy.login_identifier', 'email');
        $message = $this->build();

        self::assertStringContainsString('Login email: a***@example.test', $message);
        self::assertStringNotContainsString('Login phone:', $message);
    }

    public function testPhoneModeShowsPhone(): void
    {
        config()->set('whatsapp_onboarding.nxtutors_legacy.login_identifier', 'phone');
        $message = $this->build();

        self::assertStringContainsString('Login phone: +91******1234', $message);
        self::assertStringNotContainsString('Login email:', $message);
    }

    public function testBothModeShowsBoth(): void
    {
        config()->set('whatsapp_onboarding.nxtutors_legacy.login_identifier', 'both');
        $message = $this->build();

        self::assertStringContainsString('Login email:', $message);
        self::assertStringContainsString('Login phone:', $message);
    }

    private function build(): string
    {
        $register = new Register();
        $register->forceFill(['email' => 'asha@example.test', 'phone' => '+919999991234', 'join_as' => 'student']);

        return (new LoginMessageBuilder(new PiiMasker()))->build('student', $register, 'TempPass123', 'https://www.nxtutors.com/user/dashboard', 'Next steps');
    }
}
