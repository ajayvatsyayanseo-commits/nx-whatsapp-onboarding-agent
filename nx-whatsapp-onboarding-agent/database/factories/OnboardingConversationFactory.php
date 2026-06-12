<?php

declare(strict_types=1);

namespace NxTutors\WhatsAppOnboarding\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use NxTutors\WhatsAppOnboarding\Conversation\StateMachine\ConversationState;
use NxTutors\WhatsAppOnboarding\Profile\Models\OnboardingConversation;

/** @extends Factory<OnboardingConversation> */
final class OnboardingConversationFactory extends Factory
{
    protected $model = OnboardingConversation::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'wa_phone' => '919999' . random_int(100000, 999999),
            'role' => 'student',
            'current_state' => ConversationState::StudentName->value,
            'status' => 'open',
            'locale' => 'en',
            'context' => [],
            'last_message_at' => now(),
        ];
    }
}
