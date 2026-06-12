<?php

declare(strict_types=1);

namespace NxTutors\WhatsAppOnboarding\Tests\Unit;

use NxTutors\WhatsAppOnboarding\Security\PiiMasking\PiiMasker;
use NxTutors\WhatsAppOnboarding\Security\PiiMasking\PiiMaskingLogProcessor;
use NxTutors\WhatsAppOnboarding\Tests\TestCase;

final class PiiMaskingLogProcessorTest extends TestCase
{
    public function testMasksContextBeforeLogging(): void
    {
        $processor = new PiiMaskingLogProcessor(new PiiMasker());
        $record = $processor([
            'message' => 'user message',
            'context' => ['phone' => '+919999991234', 'email' => 'asha@example.test'],
        ]);

        self::assertStringNotContainsString('+919999991234', json_encode($record, JSON_THROW_ON_ERROR));
        self::assertStringNotContainsString('asha@example.test', json_encode($record, JSON_THROW_ON_ERROR));
    }
}
