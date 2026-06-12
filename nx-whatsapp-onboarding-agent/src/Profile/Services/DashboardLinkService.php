<?php

declare(strict_types=1);

namespace NxTutors\WhatsAppOnboarding\Profile\Services;

use NxTutors\WhatsAppOnboarding\Profile\Models\Register;
use NxTutors\WhatsAppOnboarding\Security\Passwords\SignedLoginTokenService;

final readonly class DashboardLinkService
{
    public function __construct(private SignedLoginTokenService $tokens)
    {
    }

    public function dashboardForRole(string $role, ?Register $register = null): string
    {
        $url = (string) config('whatsapp_onboarding.dashboard.' . ($role === 'tutor' ? 'tutor_url' : 'student_url'));
        if (! (bool) config('whatsapp_onboarding.dashboard.magic_login_enabled', false) || $register === null) {
            return $url;
        }

        $separator = str_contains($url, '?') ? '&' : '?';

        return $url . $separator . 'login_token=' . rawurlencode($this->tokens->issue((string) $register->user_id, $role));
    }
}
