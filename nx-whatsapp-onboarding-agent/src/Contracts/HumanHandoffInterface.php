<?php

declare(strict_types=1);

namespace NxTutors\WhatsAppOnboarding\Contracts;

use NxTutors\WhatsAppOnboarding\Profile\Models\OnboardingConversation;
use NxTutors\WhatsAppOnboarding\Profile\Models\HumanHandoffTicket;

interface HumanHandoffInterface
{
    public function openTicket(OnboardingConversation $conversation, string $reason, ?string $reasonCode = null): HumanHandoffTicket;
}
