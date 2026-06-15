<?php

declare(strict_types=1);

namespace NxTutors\WhatsAppOnboarding\Profile\Services;

use NxTutors\WhatsAppOnboarding\Contracts\UserIdGeneratorInterface;
use NxTutors\WhatsAppOnboarding\Profile\Repositories\RegisterRepository;
use RuntimeException;

final readonly class LegacyNumericUserIdGenerator implements UserIdGeneratorInterface
{
    public function __construct(private RegisterRepository $registers)
    {
    }

    public function generate(string $role): string
    {
        $maxRetries = (int) config('whatsapp_onboarding.nxtutors_legacy.user_id_max_retries', 5);
        for ($attempt = 0; $attempt < $maxRetries; $attempt++) {
            $candidate = (string) ($this->registers->maxNumericUserId() + 1 + $attempt);
            if (! $this->registers->userIdExists($candidate)) {
                return $candidate;
            }
        }

        throw new RuntimeException('Unable to generate legacy numeric user id.');
    }
}
