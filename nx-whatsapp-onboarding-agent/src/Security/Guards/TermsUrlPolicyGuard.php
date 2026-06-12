<?php

declare(strict_types=1);

namespace NxTutors\WhatsAppOnboarding\Security\Guards;

use RuntimeException;

final class TermsUrlPolicyGuard
{
    public function assertSafeConfiguration(): void
    {
        $studentUrl = (string) config('whatsapp_onboarding.terms.student_url', '');
        $tutorUrl = (string) config('whatsapp_onboarding.terms.tutor_url', '');
        $studentPrivacyUrl = (string) config('whatsapp_onboarding.terms.student_privacy_url', '');
        $tutorPrivacyUrl = (string) config('whatsapp_onboarding.terms.tutor_privacy_url', '');
        $placeholder = (string) config('whatsapp_onboarding.terms.local_placeholder_url');
        $allowPlaceholder = (bool) config('whatsapp_onboarding.terms.allow_local_placeholder', false);
        $isLocal = app()->environment(['local', 'testing']);

        if ($studentUrl === '' || $tutorUrl === '' || $studentPrivacyUrl === '' || $tutorPrivacyUrl === '') {
            throw new RuntimeException('TERMS_STUDENT_URL, TERMS_TUTOR_URL, PRIVACY_STUDENT_URL, and PRIVACY_TUTOR_URL are required.');
        }

        $usesPlaceholder = in_array($placeholder, [$studentUrl, $tutorUrl, $studentPrivacyUrl, $tutorPrivacyUrl], true);
        if ($usesPlaceholder && (! $isLocal || ! $allowPlaceholder)) {
            throw new RuntimeException('Placeholder Adobe terms URL is allowed only for local development with TERMS_ALLOW_LOCAL_PLACEHOLDER=true.');
        }
    }
}
