<?php

declare(strict_types=1);

namespace NxTutors\WhatsAppOnboarding\Tests\Unit;

use Illuminate\Support\Facades\Hash;
use NxTutors\WhatsAppOnboarding\Profile\Services\LoginCredentialService;
use NxTutors\WhatsAppOnboarding\Tests\TestCase;

final class LoginCredentialServiceTest extends TestCase
{
    public function testGeneratesTemporaryPasswordAndHashOnly(): void
    {
        $result = (new LoginCredentialService())->generateTemporaryPassword();

        self::assertArrayHasKey('temporary_password', $result);
        self::assertArrayHasKey('password_hash', $result);
        self::assertNotSame($result['temporary_password'], $result['password_hash']);
        self::assertTrue(Hash::check($result['temporary_password'], $result['password_hash']));
    }
}
