<?php

declare(strict_types=1);

namespace NxTutors\WhatsAppOnboarding\Contracts;

use NxTutors\WhatsAppOnboarding\Profile\Models\OnboardingConversation;
use NxTutors\WhatsAppOnboarding\Security\Otp\OtpVerificationResult;

interface OtpServiceInterface
{
    public function issue(OnboardingConversation $conversation): string;

    public function verify(OnboardingConversation $conversation, string $otp): OtpVerificationResult;
}
