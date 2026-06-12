<?php

declare(strict_types=1);

namespace NxTutors\WhatsAppOnboarding\Security\Otp;

use NxTutors\WhatsAppOnboarding\Contracts\OtpServiceInterface;
use NxTutors\WhatsAppOnboarding\Profile\Models\OnboardingConversation;

final class DatabaseOtpService implements OtpServiceInterface
{
    public function issue(OnboardingConversation $conversation): string
    {
        $length = (int) config('whatsapp_onboarding_security.otp.length', 6);
        $otp = str_pad((string) random_int(0, (10 ** $length) - 1), $length, '0', STR_PAD_LEFT);
        $context = $conversation->context ?? [];
        $context['otp_hash'] = $this->hash($otp);
        $context['otp_issued_at'] = now()->toIso8601String();
        $context['otp_attempts'] = 0;
        $conversation->forceFill(['context' => $context])->save();

        return $otp;
    }

    public function verify(OnboardingConversation $conversation, string $otp): OtpVerificationResult
    {
        $context = $conversation->context ?? [];
        $expected = (string) ($context['otp_hash'] ?? '');
        if ($expected === '') {
            return OtpVerificationResult::invalid();
        }

        $attempts = (int) ($context['otp_attempts'] ?? 0);
        if ($attempts >= (int) config('whatsapp_onboarding_security.otp.max_attempts', 3)) {
            return OtpVerificationResult::tooManyAttempts();
        }

        $issuedAt = isset($context['otp_issued_at']) ? \DateTimeImmutable::createFromFormat(\DateTimeInterface::ATOM, (string) $context['otp_issued_at']) : false;
        $ttlMinutes = (int) config('whatsapp_onboarding_state_machine.otp_ttl_minutes', 10);
        if ($issuedAt === false || $issuedAt->modify("+{$ttlMinutes} minutes") < new \DateTimeImmutable('now')) {
            return OtpVerificationResult::expired();
        }

        $valid = hash_equals($expected, $this->hash(trim($otp)));
        if ($valid) {
            $conversation->forceFill(['otp_verified_at' => now()])->save();
            return OtpVerificationResult::valid();
        }

        $context['otp_attempts'] = $attempts + 1;
        $conversation->forceFill(['context' => $context])->save();

        if ($context['otp_attempts'] >= (int) config('whatsapp_onboarding_security.otp.max_attempts', 3)) {
            return OtpVerificationResult::tooManyAttempts();
        }

        return OtpVerificationResult::invalid();
    }

    private function hash(string $otp): string
    {
        return hash_hmac('sha256', $otp, (string) config('app.key', 'local-testing-key'));
    }
}
