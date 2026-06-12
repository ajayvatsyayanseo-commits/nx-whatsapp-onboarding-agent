<?php

declare(strict_types=1);

namespace NxTutors\WhatsAppOnboarding\Tests\Feature;

use NxTutors\WhatsAppOnboarding\Profile\Exceptions\DuplicateRegisterFieldException;
use NxTutors\WhatsAppOnboarding\Tests\TestCase;

final class DuplicateProfileHandlingTest extends TestCase
{
    public function testDuplicateExceptionCarriesSafeFieldNameOnly(): void
    {
        $exception = new DuplicateRegisterFieldException('document_number');

        self::assertSame('document_number', $exception->field);
        self::assertStringNotContainsString('account', $exception->getMessage());
    }
}
