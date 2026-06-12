<?php

declare(strict_types=1);

namespace NxTutors\WhatsAppOnboarding\Conversation\Services;

use NxTutors\WhatsAppOnboarding\Contracts\HumanHandoffInterface;
use NxTutors\WhatsAppOnboarding\Contracts\AdminNotificationInterface;
use NxTutors\WhatsAppOnboarding\Conversation\StateMachine\ConversationState;
use NxTutors\WhatsAppOnboarding\Observability\Logging\OnboardingAuditLogger;
use NxTutors\WhatsAppOnboarding\Profile\Models\HumanHandoffTicket;
use NxTutors\WhatsAppOnboarding\Profile\Models\OnboardingConversation;

final readonly class HumanHandoffService implements HumanHandoffInterface
{
    public function __construct(
        private OnboardingAuditLogger $audit,
        private AdminNotificationInterface $notifier,
    ) {
    }

    public function openTicket(OnboardingConversation $conversation, string $reason, ?string $reasonCode = null): HumanHandoffTicket
    {
        $ticket = HumanHandoffTicket::query()->firstOrCreate(
            [
                'onboarding_conversation_id' => $conversation->id,
                'status' => 'open',
            ],
            [
                'wa_phone' => $conversation->wa_phone,
                'role' => $conversation->role,
                'reason' => $reason,
                'reason_code' => $reasonCode,
                'opened_at' => now(),
            ],
        );

        $conversation->forceFill([
            'current_state' => ConversationState::HumanHandoff->value,
            'status' => 'handoff',
        ])->save();

        $this->audit->log($conversation->refresh(), 'human_handoff_opened', ['reason' => $reason, 'reason_code' => $reasonCode, 'ticket_id' => $ticket->id]);
        $this->notifier->notifyHumanHandoff($ticket);

        return $ticket;
    }
}
