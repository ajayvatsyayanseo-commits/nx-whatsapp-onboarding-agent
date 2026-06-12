<?php

declare(strict_types=1);

namespace NxTutors\WhatsAppOnboarding\Conversation\Repositories;

use NxTutors\WhatsAppOnboarding\Contracts\StateRepositoryInterface;
use NxTutors\WhatsAppOnboarding\Conversation\StateMachine\ConversationState;
use NxTutors\WhatsAppOnboarding\Observability\Logging\OnboardingAuditLogger;
use NxTutors\WhatsAppOnboarding\Profile\Models\OnboardingConversation;

final readonly class PostgresStateRepository implements StateRepositoryInterface
{
    public function __construct(private OnboardingAuditLogger $audit)
    {
    }

    public function findOpenByPhone(string $waPhone): ?OnboardingConversation
    {
        return OnboardingConversation::query()
            ->where('wa_phone', $waPhone)
            ->where('status', 'open')
            ->latest('id')
            ->first();
    }

    public function findOpenByPhoneForUpdate(string $waPhone): ?OnboardingConversation
    {
        return OnboardingConversation::query()
            ->where('wa_phone', $waPhone)
            ->where('status', 'open')
            ->latest('id')
            ->lockForUpdate()
            ->first();
    }

    public function startOrResume(string $waPhone, array $attributes = []): OnboardingConversation
    {
        $conversation = $this->findOpenByPhone($waPhone);
        if ($conversation !== null) {
            return $conversation;
        }

        return OnboardingConversation::query()->create(array_merge([
            'wa_phone' => $waPhone,
            'current_state' => ConversationState::New->value,
            'status' => 'open',
            'locale' => 'en',
            'context' => [],
            'field_attempts' => [],
            'last_message_at' => now(),
        ], $attributes));
    }

    public function transition(OnboardingConversation $conversation, ConversationState $state, array $context = []): OnboardingConversation
    {
        $mergedContext = array_merge($conversation->context ?? [], $context);
        $conversation->forceFill([
            'current_state' => $state->value,
            'role' => $mergedContext['role'] ?? $conversation->role,
            'context' => $mergedContext,
            'lock_version' => ((int) $conversation->lock_version) + 1,
            'last_message_at' => now(),
        ])->save();

        $fresh = $conversation->refresh();
        $this->audit->log($fresh, 'state_transition', [
            'to' => $state->value,
            'context' => $context,
            'lock_version' => $fresh->lock_version,
        ]);

        return $fresh;
    }

    public function markCompleted(OnboardingConversation $conversation): OnboardingConversation
    {
        $conversation->forceFill([
            'status' => 'completed',
            'current_state' => ConversationState::Completed->value,
            'lock_version' => ((int) $conversation->lock_version) + 1,
            'completed_at' => now(),
        ])->save();

        $fresh = $conversation->refresh();
        $this->audit->log($fresh, 'conversation_completed');

        return $fresh;
    }

    public function cancel(OnboardingConversation $conversation): OnboardingConversation
    {
        $conversation->forceFill([
            'status' => 'cancelled',
            'current_state' => ConversationState::Cancelled->value,
            'lock_version' => ((int) $conversation->lock_version) + 1,
        ])->save();

        $fresh = $conversation->refresh();
        $this->audit->log($fresh, 'conversation_cancelled');

        return $fresh;
    }
}
