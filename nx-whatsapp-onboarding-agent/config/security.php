<?php

declare(strict_types=1);

return [
    'password' => [
        'temporary_length' => (int) env('WHATSAPP_ONBOARDING_TEMP_PASSWORD_LENGTH', 18),
        'force_reset_column' => env('WHATSAPP_ONBOARDING_FORCE_RESET_COLUMN', 'force_password_reset'),
        'hash_driver' => env('HASH_DRIVER', 'bcrypt'),
    ],
    'otp' => [
        'length' => (int) env('WHATSAPP_ONBOARDING_OTP_LENGTH', 6),
        'max_attempts' => (int) env('WHATSAPP_ONBOARDING_OTP_MAX_ATTEMPTS', 3),
        'resend_cooldown_seconds' => (int) env('WHATSAPP_ONBOARDING_OTP_RESEND_COOLDOWN_SECONDS', 60),
    ],
    'pii_masking' => [
        'enabled' => env('WHATSAPP_ONBOARDING_MASK_PII_LOGS', true),
        'masked_keys' => ['phone', 'wa_phone', 'email', 'document_number', 'otp', 'password', 'temporary_password', 'address', 'dob'],
    ],
    'webhook' => [
        'signature_header' => 'X-Hub-Signature-256',
        'max_body_bytes' => (int) env('WHATSAPP_ONBOARDING_MAX_WEBHOOK_BYTES', 262144),
    ],
    'input_guardrails' => [
        'max_message_length' => (int) env('WHATSAPP_ONBOARDING_MAX_MESSAGE_LENGTH', 2000),
        'rate_limit_per_phone_per_minute' => (int) env('WHATSAPP_ONBOARDING_INBOUND_PER_PHONE_MINUTE', 30),
        'rate_limit_per_ip_per_minute' => (int) env('WHATSAPP_ONBOARDING_INBOUND_PER_IP_MINUTE', 120),
        'block_injection_patterns' => env('WHATSAPP_ONBOARDING_BLOCK_INJECTION_PATTERNS', true),
    ],
    'validation' => [
        'india_pincode' => env('WHATSAPP_ONBOARDING_INDIA_PINCODE', true),
    ],
    'draft_encryption' => [
        'enabled' => env('WHATSAPP_ONBOARDING_ENCRYPT_SENSITIVE_DRAFTS', true),
    ],
    'pause' => [
        'outbound_paused' => env('WHATSAPP_OUTBOUND_PAUSED', false),
        'onboarding_paused' => env('WHATSAPP_ONBOARDING_PAUSED', false),
        'reason' => env('WHATSAPP_ONBOARDING_PAUSE_REASON'),
    ],
];
