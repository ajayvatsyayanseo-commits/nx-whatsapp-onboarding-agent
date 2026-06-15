<?php

declare(strict_types=1);

namespace NxTutors\WhatsAppOnboarding\Profile\Services;

use NxTutors\WhatsAppOnboarding\Profile\Models\Register;
use NxTutors\WhatsAppOnboarding\Security\PiiMasking\PiiMasker;

final readonly class LoginMessageBuilder
{
    public function __construct(private PiiMasker $masker)
    {
    }

    public function build(string $role, Register $register, string $temporaryPassword, string $dashboard, string $checklist): string
    {
        $isTutor = $role === 'tutor' || (string) $register->join_as === 'teacher';
        $title = $isTutor ? 'Your NXtutors tutor account is ready.' : 'Your NXtutors student account is ready.';
        $dashboardLabel = $isTutor ? 'Tutor dashboard after login' : 'Dashboard after login';
        $loginLines = $this->loginIdentifierLines($register);
        $loginUrl = (string) config('whatsapp_onboarding.dashboard.login_url', 'https://www.nxtutors.com/login');
        $tail = $isTutor
            ? 'Please complete your tutor profile/documents after login.'
            : 'Please change your password after login.';

        return trim(implode("\n", array_filter([
            $title,
            '',
            'Login page: ' . $loginUrl,
            $loginLines,
            'Temporary password: ' . $temporaryPassword,
            '',
            $dashboardLabel . ':',
            $dashboard,
            '',
            'First login, then dashboard will open.',
            $tail,
            '',
            $checklist,
        ], static fn (?string $line): bool => $line !== null)));
    }

    private function loginIdentifierLines(Register $register): string
    {
        $mode = (string) config('whatsapp_onboarding.nxtutors_legacy.login_identifier', 'email');
        $lines = [];

        if ($mode === 'email' || $mode === 'both') {
            $lines[] = 'Login email: ' . $this->masker->maskValue('email', (string) $register->email);
        }

        if ($mode === 'phone' || $mode === 'both') {
            $lines[] = 'Login phone: ' . $this->masker->maskValue('phone', (string) $register->phone);
        }

        return implode("\n", $lines);
    }
}
