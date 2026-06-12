<?php

declare(strict_types=1);

namespace NxTutors\WhatsAppOnboarding\Profile\Services;

use NxTutors\WhatsAppOnboarding\Profile\Repositories\RegisterRepository;
use RuntimeException;

final readonly class UserIdGenerator
{
    public function __construct(private RegisterRepository $registers)
    {
    }

    public function generate(string $role): string
    {
        $prefix = $role === 'tutor'
            ? (string) config('whatsapp_onboarding.profile.user_id_prefix_tutor', 'NXT')
            : (string) config('whatsapp_onboarding.profile.user_id_prefix_student', 'NXS');

        $length = (int) config('whatsapp_onboarding.profile.user_id_random_length', 6);
        for ($attempt = 0; $attempt < 10; $attempt++) {
            $candidate = sprintf('%s-%s-%s', $prefix, date('Y'), $this->randomSuffix($length));
            if (! $this->registers->userIdExists($candidate)) {
                return $candidate;
            }
        }

        throw new RuntimeException('Unable to generate unique NXtutors user id.');
    }

    private function randomSuffix(int $length): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $value = '';
        for ($i = 0; $i < $length; $i++) {
            $value .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }

        return $value;
    }
}
