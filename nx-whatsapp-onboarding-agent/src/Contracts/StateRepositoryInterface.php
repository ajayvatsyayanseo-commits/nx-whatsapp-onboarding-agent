<?php

declare(strict_types=1);

namespace NxTutors\WhatsAppOnboarding\Contracts;

use NxTutors\WhatsAppOnboarding\Conversation\StateMachine\ConversationState;
use NxTutors\WhatsAppOnboarding\Profile\Models\OnboardingConversation;

interface StateRepositoryInterface
{
    public function findOpenByPhone(string $waPhone): ?OnboardingConversation;

    public function findOpenByPhoneForUpdate(string $waPhone): ?OnboardingConversation;

    /** @param array<string, mixed> $attributes */
    public function startOrResume(string $waPhone, array $attributes = []): OnboardingConversation;

    /** @param array<string, mixed> $context */
    public function transition(OnboardingConversation $conversation, ConversationState $state, array $context = []): OnboardingConversation;

    public function markCompleted(OnboardingConversation $conversation): OnboardingConversation;

    public function cancel(OnboardingConversation $conversation): OnboardingConversation;
}
