<?php

declare(strict_types=1);

namespace NxTutors\WhatsAppOnboarding\Profile\Services;

use NxTutors\WhatsAppOnboarding\Contracts\UserIdGeneratorInterface;

final readonly class UserIdGenerator
{
    public function __construct(
        private LegacyNumericUserIdGenerator $legacy,
        private PrefixedRandomUserIdGenerator $prefixed,
    )
    {
    }

    public function generate(string $role): string
    {
        return $this->driver()->generate($role);
    }

    private function driver(): UserIdGeneratorInterface
    {
        return (string) config('whatsapp_onboarding.nxtutors_legacy.user_id_mode', 'legacy_numeric') === 'legacy_numeric'
            ? $this->legacy
            : $this->prefixed;
    }
}
