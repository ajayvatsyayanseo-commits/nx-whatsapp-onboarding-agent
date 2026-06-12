<?php

declare(strict_types=1);

namespace NxTutors\WhatsAppOnboarding\Tests\Unit;

use NxTutors\WhatsAppOnboarding\Tests\TestCase;

final class ConfigLoadingTest extends TestCase
{
    public function testConfigContainsRequiredTermsAndMetaKeys(): void
    {
        $config = require __DIR__ . '/../../config/whatsapp_onboarding.php';

        self::assertArrayHasKey('meta', $config);
        self::assertArrayHasKey('terms', $config);
        self::assertArrayHasKey('student_url', $config['terms']);
        self::assertArrayHasKey('tutor_url', $config['terms']);
    }
}
