<?php

declare(strict_types=1);

namespace NxTutors\WhatsAppOnboarding\Tests\Unit;

use NxTutors\WhatsAppOnboarding\Common\Support\PhoneNormalizer;
use PHPUnit\Framework\TestCase;

final class PhoneNormalizerTest extends TestCase
{
    public function testBuildsIndianPhoneVariants(): void
    {
        $variants = (new PhoneNormalizer())->variants('+91 98765 43210');

        self::assertContains('9876543210', $variants);
        self::assertContains('919876543210', $variants);
        self::assertContains('+919876543210', $variants);
    }
}
