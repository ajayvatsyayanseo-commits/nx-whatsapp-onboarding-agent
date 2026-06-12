<?php

declare(strict_types=1);

namespace NxTutors\WhatsAppOnboarding\Database\Seeders;

use Illuminate\Database\Seeder;
use NxTutors\WhatsAppOnboarding\Conversation\StateMachine\ConversationState;
use NxTutors\WhatsAppOnboarding\Profile\Models\OnboardingConversation;

final class DemoConversationSeeder extends Seeder
{
    public function run(): void
    {
        OnboardingConversation::query()->updateOrCreate(
            ['wa_phone' => '919999000001', 'status' => 'open'],
            [
                'role' => 'student',
                'current_state' => ConversationState::StudentName->value,
                'locale' => 'en',
                'context' => ['wa_phone' => '919999000001', 'role' => 'student'],
                'last_message_at' => now(),
            ],
        );

        OnboardingConversation::query()->updateOrCreate(
            ['wa_phone' => '919999000002', 'status' => 'open'],
            [
                'role' => 'tutor',
                'current_state' => ConversationState::TutorEducation->value,
                'locale' => 'en',
                'context' => ['wa_phone' => '919999000002', 'role' => 'tutor', 'name' => 'Demo Tutor'],
                'last_message_at' => now(),
            ],
        );
    }
}
