<?php

declare(strict_types=1);

namespace NxTutors\WhatsAppOnboarding\Tests\Unit;

use NxTutors\WhatsAppOnboarding\Profile\Repositories\RegisterRepository;
use NxTutors\WhatsAppOnboarding\Profile\Services\LegacyNumericUserIdGenerator;
use NxTutors\WhatsAppOnboarding\Profile\Services\PrefixedRandomUserIdGenerator;
use NxTutors\WhatsAppOnboarding\Profile\Services\UserIdGenerator;
use NxTutors\WhatsAppOnboarding\Tests\TestCase;

final class UserIdGeneratorTest extends TestCase
{
    public function testGeneratesLegacyNumericIdByDefault(): void
    {
        $repo = new class extends RegisterRepository {
            public function __construct()
            {
            }

            public function userIdExists(string $userId): bool
            {
                return false;
            }

            public function maxNumericUserId(): int
            {
                return 100;
            }
        };

        $generator = new UserIdGenerator(new LegacyNumericUserIdGenerator($repo), new PrefixedRandomUserIdGenerator($repo));

        self::assertSame('101', $generator->generate('student'));
    }
}
