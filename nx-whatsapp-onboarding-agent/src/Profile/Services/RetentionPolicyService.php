<?php

declare(strict_types=1);

namespace NxTutors\WhatsAppOnboarding\Profile\Services;

use NxTutors\WhatsAppOnboarding\Conversation\StateMachine\ConversationState;
use NxTutors\WhatsAppOnboarding\Profile\Models\OnboardingConversation;
use NxTutors\WhatsAppOnboarding\Profile\Models\OnboardingEvent;

final class RetentionPolicyService
{
    public function expireOldDrafts(): int
    {
        $days = (int) config('whatsapp_onboarding_observability.retention.incomplete_draft_days', 14);

        return OnboardingConversation::query()
            ->where('status', 'open')
            ->where('updated_at', '<', now()->subDays($days))
            ->update([
                'status' => 'expired',
                'current_state' => ConversationState::Expired->value,
                'context' => [],
            ]);
    }

    public function purgeOldRawWebhookPayloads(): int
    {
        $days = (int) config('whatsapp_onboarding_observability.retention.raw_webhook_payload_days', 7);

        return OnboardingEvent::query()
            ->where('direction', 'inbound')
            ->where('created_at', '<', now()->subDays($days))
            ->update(['payload' => ['purged' => true]]);
    }
}
