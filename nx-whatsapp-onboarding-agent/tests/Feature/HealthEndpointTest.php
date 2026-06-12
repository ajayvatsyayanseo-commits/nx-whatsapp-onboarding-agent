<?php

declare(strict_types=1);

namespace NxTutors\WhatsAppOnboarding\Tests\Feature;

use NxTutors\WhatsAppOnboarding\Health\HealthCheckService;
use NxTutors\WhatsAppOnboarding\Tests\TestCase;

final class HealthEndpointTest extends TestCase
{
    public function testLivenessShape(): void
    {
        $health = new HealthCheckService();

        self::assertSame('nx-whatsapp-onboarding', $health->live()['service']);
    }
}
