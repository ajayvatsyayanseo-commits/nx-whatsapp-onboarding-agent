<?php

declare(strict_types=1);

return [
    'llm_enabled' => env('WHATSAPP_ONBOARDING_LLM_ENABLED', false),
    'llm_max_calls_per_conversation' => (int) env('LLM_MAX_CALLS_PER_CONVERSATION', 2),
    'llm_max_tokens_per_call' => (int) env('LLM_MAX_TOKENS_PER_CALL', 600),
    'llm_timeout_ms' => (int) env('LLM_TIMEOUT_MS', 1500),
    'llm_daily_budget' => (float) env('LLM_DAILY_BUDGET_INR', env('LLM_DAILY_BUDGET_USD', 0)),
    'llm_latency_breaker_ms' => (int) env('LLM_LATENCY_BREAKER_MS', 2500),
    'llm_error_rate_breaker_percent' => (float) env('LLM_ERROR_RATE_BREAKER_PERCENT', 10),
    'llm_daily_token_budget' => (int) env('WHATSAPP_ONBOARDING_LLM_DAILY_TOKEN_BUDGET', 0),
    'max_outbound_messages_per_phone_per_hour' => (int) env('WHATSAPP_ONBOARDING_MAX_MESSAGES_PER_PHONE_HOUR', 20),
    'max_outbound_messages_global_per_minute' => (int) env('WHATSAPP_ONBOARDING_MAX_MESSAGES_GLOBAL_MINUTE', 1000),
    'max_media_download_mb' => (int) env('WHATSAPP_ONBOARDING_MAX_MEDIA_DOWNLOAD_MB', 10),
    'meta_circuit_breaker' => [
        'failure_threshold' => (int) env('META_CIRCUIT_BREAKER_FAILURE_THRESHOLD', 5),
        'cooldown_seconds' => (int) env('META_CIRCUIT_BREAKER_COOLDOWN_SECONDS', 60),
    ],
];
