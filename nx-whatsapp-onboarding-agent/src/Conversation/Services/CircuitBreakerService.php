<?php

declare(strict_types=1);

namespace NxTutors\WhatsAppOnboarding\Conversation\Services;

use NxTutors\WhatsAppOnboarding\Profile\Models\OnboardingConversation;

final class CircuitBreakerService
{
    public function shouldHandoff(OnboardingConversation $conversation, ?string $field = null): bool
    {
        if ($field !== null) {
            $attempts = $conversation->field_attempts ?? [];

            return (int) ($attempts[$field] ?? 0) >= (int) config('whatsapp_onboarding_state_machine.max_invalid_attempts_per_field', 3);
        }

        return (int) $conversation->invalid_attempts >= (int) config('whatsapp_onboarding_state_machine.max_invalid_attempts', 3);
    }

    public function recordInvalidAttempt(OnboardingConversation $conversation, ?string $field = null): void
    {
        $updates = ['invalid_attempts' => ((int) $conversation->invalid_attempts) + 1];
        if ($field !== null) {
            $attempts = $conversation->field_attempts ?? [];
            $attempts[$field] = ((int) ($attempts[$field] ?? 0)) + 1;
            $updates['field_attempts'] = $attempts;
        }

        $conversation->forceFill($updates)->save();
    }

    public function reset(OnboardingConversation $conversation, ?string $field = null): void
    {
        $updates = [];
        if ((int) $conversation->invalid_attempts > 0) {
            $updates['invalid_attempts'] = 0;
        }

        if ($field !== null) {
            $attempts = $conversation->field_attempts ?? [];
            unset($attempts[$field]);
            $updates['field_attempts'] = $attempts;
        }

        if ($updates !== []) {
            $conversation->forceFill($updates)->save();
        }
    }
}
