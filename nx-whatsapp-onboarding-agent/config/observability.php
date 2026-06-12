<?php

declare(strict_types=1);

return [
    'log_channel' => env('WHATSAPP_ONBOARDING_LOG_CHANNEL', env('LOG_CHANNEL', 'stack')),
    'metrics_namespace' => env('WHATSAPP_ONBOARDING_METRICS_NAMESPACE', 'NXtutors/WhatsAppOnboarding'),
    'trace_sample_rate' => (float) env('WHATSAPP_ONBOARDING_TRACE_SAMPLE_RATE', 0.05),
    'audit_enabled' => env('WHATSAPP_ONBOARDING_AUDIT_ENABLED', true),
    'retention' => [
        'incomplete_draft_days' => (int) env('WHATSAPP_ONBOARDING_INCOMPLETE_DRAFT_RETENTION_DAYS', 14),
        'raw_webhook_payload_days' => (int) env('WHATSAPP_ONBOARDING_RAW_WEBHOOK_RETENTION_DAYS', 7),
        'store_raw_payload' => env('WHATSAPP_ONBOARDING_STORE_RAW_WEBHOOK_PAYLOAD', true),
        's3_raw_payload_export_enabled' => env('WHATSAPP_ONBOARDING_S3_RAW_PAYLOAD_EXPORT_ENABLED', false),
    ],
    'drift' => [
        'min_completion_rate_percent' => (float) env('WHATSAPP_ONBOARDING_DRIFT_MIN_COMPLETION_RATE', 40),
        'max_handoff_rate_percent' => (float) env('WHATSAPP_ONBOARDING_DRIFT_MAX_HANDOFF_RATE', 25),
        'max_meta_failures' => (int) env('WHATSAPP_ONBOARDING_DRIFT_MAX_META_FAILURES', 20),
    ],
];
