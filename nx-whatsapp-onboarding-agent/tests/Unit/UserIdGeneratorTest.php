<?php

declare(strict_types=1);

namespace NxTutors\WhatsAppOnboarding\Tests\Unit;

use NxTutors\WhatsAppOnboarding\Profile\Repositories\RegisterRepository;
use NxTutors\WhatsAppOnboarding\Profile\Services\UserIdGenerator;
use NxTutors\WhatsAppOnboarding\Tests\TestCase;

final class UserIdGeneratorTest extends TestCase
{
    public function testGeneratesConfiguredStudentAndTutorFormats(): void
    {
        $generator = new UserIdGenerator(new class extends RegisterRepository {
            public function userIdExists(string $userId): bool
            {
                return false;
            }
        });

        self::assertMatchesRegularExpression('/^NXS-[0-9]{4}-[A-Z2-9]{6}$/', $generator->generate('student'));
        self::assertMatchesRegularExpression('/^NXT-[0-9]{4}-[A-Z2-9]{6}$/', $generator->generate('tutor'));
    }
}
