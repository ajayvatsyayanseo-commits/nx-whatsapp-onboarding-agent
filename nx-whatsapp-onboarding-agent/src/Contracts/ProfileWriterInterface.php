<?php

declare(strict_types=1);

namespace NxTutors\WhatsAppOnboarding\Contracts;

use NxTutors\WhatsAppOnboarding\Profile\Models\OnboardingConversation;
use NxTutors\WhatsAppOnboarding\Profile\DTO\ProfileWriteResult;

interface ProfileWriterInterface
{
    public function write(OnboardingConversation $conversation): ProfileWriteResult;
}
