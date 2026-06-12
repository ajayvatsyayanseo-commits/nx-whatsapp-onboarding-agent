<?php

declare(strict_types=1);

namespace NxTutors\WhatsAppOnboarding\Profile\Services;

use Illuminate\Support\Facades\Cache;

final class ProfileFeatureFlagService
{
    public function signupEnabled(): bool
    {
        return ! (bool) Cache::get('nxtutors:onboarding:paused', false)
            && ! (bool) config('whatsapp_onboarding_security.pause.onboarding_paused', false)
            && (bool) config('whatsapp_onboarding.profile.signup_enabled', true);
    }

    public function roleEnabled(string $role): bool
    {
        return $role === 'tutor'
            ? (bool) config('whatsapp_onboarding.profile.tutor_signup_enabled', true)
            : (bool) config('whatsapp_onboarding.profile.student_signup_enabled', true);
    }
}
