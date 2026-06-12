<?php

declare(strict_types=1);

namespace NxTutors\WhatsAppOnboarding\Tests\Unit;

use NxTutors\WhatsAppOnboarding\Security\PiiMasking\PiiMasker;
use PHPUnit\Framework\TestCase;

final class PiiMaskerTest extends TestCase
{
    public function testMasksSensitiveFields(): void
    {
        $masker = new PiiMasker();

        self::assertSame('+91******1234', $masker->maskValue('phone', '+919999991234'));
        self::assertSame('a***@example.test', $masker->maskValue('email', 'asha@example.test'));
        self::assertSame('[redacted]', $masker->maskValue('temporary_password', 'secret'));
        self::assertSame('[masked address]', $masker->maskValue('address', 'Raw address'));
        self::assertSame('[masked date]', $masker->maskValue('dob', '2000-01-01'));
    }
}
