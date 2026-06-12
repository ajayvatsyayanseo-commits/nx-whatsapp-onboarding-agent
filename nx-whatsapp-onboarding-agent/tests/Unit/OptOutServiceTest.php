<?php

declare(strict_types=1);

namespace NxTutors\WhatsAppOnboarding\Tests\Unit;

use Illuminate\Support\Facades\Cache;
use NxTutors\WhatsAppOnboarding\Tests\TestCase;
use NxTutors\WhatsAppOnboarding\WhatsApp\Services\WhatsAppOptOutService;

final class OptOutServiceTest extends TestCase
{
    public function testStopAndUnsubscribeAreOptOutCommands(): void
    {
        $service = new WhatsAppOptOutService(Cache::store());

        self::assertTrue($service->isStopCommand('STOP'));
        self::assertTrue($service->isStopCommand('unsubscribe'));
    }
}
