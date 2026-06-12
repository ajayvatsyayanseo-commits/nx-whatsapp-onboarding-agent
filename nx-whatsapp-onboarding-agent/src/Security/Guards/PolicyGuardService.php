<?php

declare(strict_types=1);

namespace NxTutors\WhatsAppOnboarding\Security\Guards;

use NxTutors\WhatsAppOnboarding\Contracts\PolicyGuardInterface;
use NxTutors\WhatsAppOnboarding\Profile\Services\ProfileFeatureFlagService;
use NxTutors\WhatsAppOnboarding\Security\AbuseDetection\AbuseDetector;
use RuntimeException;

final readonly class PolicyGuardService implements PolicyGuardInterface
{
    public function __construct(
        private ProfileFeatureFlagService $features,
        private AbuseDetector $abuseDetector,
        private TermsUrlPolicyGuard $termsGuard,
    ) {
    }

    public function assertSafeConfiguration(): void
    {
        $this->termsGuard->assertSafeConfiguration();
    }

    public function assertCanStart(?string $text): void
    {
        $normalized = mb_strtoupper(trim((string) $text));
        if (in_array($normalized, ['STOP', 'UNSUBSCRIBE'], true)) {
            return;
        }

        if ((bool) config('whatsapp_onboarding_security.pause.onboarding_paused', false)) {
            throw new RuntimeException('WhatsApp onboarding is paused: ' . (string) config('whatsapp_onboarding_security.pause.reason'));
        }

        if (! $this->features->signupEnabled()) {
            throw new RuntimeException('WhatsApp signup is disabled.');
        }

        if ($this->abuseDetector->isSuspicious((string) $text)) {
            throw new RuntimeException('Inbound message blocked by policy guard.');
        }
    }

    public function assertRoleEnabled(string $role): void
    {
        if (! $this->features->roleEnabled($role)) {
            throw new RuntimeException("WhatsApp {$role} signup is disabled.");
        }
    }
}
