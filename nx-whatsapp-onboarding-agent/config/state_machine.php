<?php

declare(strict_types=1);

use NxTutors\WhatsAppOnboarding\Conversation\StateMachine\ConversationState;

return [
    'initial_state' => ConversationState::New->value,
    'terminal_states' => [
        ConversationState::Completed->value,
        ConversationState::HumanHandoff->value,
        ConversationState::Cancelled->value,
        ConversationState::Expired->value,
        ConversationState::ErrorFinal->value,
    ],
    'max_invalid_attempts' => (int) env('WHATSAPP_ONBOARDING_MAX_INVALID_ATTEMPTS', 3),
    'max_invalid_attempts_per_field' => (int) env('WHATSAPP_ONBOARDING_MAX_INVALID_ATTEMPTS_PER_FIELD', 3),
    'conversation_ttl_minutes' => (int) env('WHATSAPP_ONBOARDING_CONVERSATION_TTL_MINUTES', 10080),
    'redis_ttl_seconds' => (int) env('WHATSAPP_ONBOARDING_REDIS_TTL_SECONDS', 86400),
    'otp_ttl_minutes' => (int) env('WHATSAPP_ONBOARDING_OTP_TTL_MINUTES', 10),
    'min_age_years' => env('WHATSAPP_ONBOARDING_MIN_AGE_YEARS') !== null ? (int) env('WHATSAPP_ONBOARDING_MIN_AGE_YEARS') : null,
];
