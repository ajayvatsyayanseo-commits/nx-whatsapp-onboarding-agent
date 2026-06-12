<?php

declare(strict_types=1);

namespace NxTutors\WhatsAppOnboarding\Observability\Logging;

use NxTutors\WhatsAppOnboarding\Profile\Models\OnboardingAuditLog;
use NxTutors\WhatsAppOnboarding\Profile\Models\OnboardingConversation;
use NxTutors\WhatsAppOnboarding\Security\PiiMasking\PiiMasker;

final readonly class OnboardingAuditLogger
{
    public function __construct(private PiiMasker $masker)
    {
    }

    /** @param array<string, mixed> $metadata */
    public function log(OnboardingConversation $conversation, string $action, array $metadata = [], string $actor = 'system'): void
    {
        OnboardingAuditLog::query()->create([
            'onboarding_conversation_id' => $conversation->id,
            'action' => $action,
            'actor' => $actor,
            'masked_metadata' => $this->masker->maskArray($metadata),
            'created_at' => now(),
        ]);
    }
}
