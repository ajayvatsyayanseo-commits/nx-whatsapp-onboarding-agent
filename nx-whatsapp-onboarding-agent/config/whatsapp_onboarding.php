<?php

declare(strict_types=1);

return [
    'enabled' => env('WHATSAPP_ONBOARDING_ENABLED', true),
    'route_prefix' => env('WHATSAPP_ONBOARDING_ROUTE_PREFIX', 'whatsapp/onboarding'),
    'queue' => env('WHATSAPP_ONBOARDING_QUEUE', 'whatsapp-onboarding'),
    'database_connection' => env('WHATSAPP_ONBOARDING_DB_CONNECTION', env('DB_CONNECTION', 'pgsql')),
    'redis_connection' => env('WHATSAPP_ONBOARDING_REDIS_CONNECTION', env('REDIS_CONNECTION', 'default')),

    'meta' => [
        'verify_token' => env('META_WHATSAPP_VERIFY_TOKEN'),
        'app_secret' => env('META_WHATSAPP_APP_SECRET'),
        'access_token' => env('META_WHATSAPP_ACCESS_TOKEN'),
        'phone_number_id' => env('META_WHATSAPP_PHONE_NUMBER_ID'),
        'api_version' => env('META_WHATSAPP_API_VERSION', 'v20.0'),
        'graph_base_url' => env('META_GRAPH_BASE_URL', 'https://graph.facebook.com'),
        'interactive_enabled' => env('META_WHATSAPP_INTERACTIVE_ENABLED', true),
        'require_template_outside_session' => env('META_REQUIRE_TEMPLATE_OUTSIDE_SESSION', true),
        'template_language' => env('META_WHATSAPP_TEMPLATE_LANGUAGE', 'en_US'),
        'templates' => [
            'signup_resume' => env('META_TEMPLATE_SIGNUP_RESUME', 'signup_resume'),
            'otp_message' => env('META_TEMPLATE_OTP_MESSAGE', 'otp_message'),
            'profile_created' => env('META_TEMPLATE_PROFILE_CREATED', 'profile_created'),
            'human_handoff' => env('META_TEMPLATE_HUMAN_HANDOFF', 'human_handoff'),
        ],
    ],

    'terms' => [
        'student_url' => env('TERMS_STUDENT_URL'),
        'student_privacy_url' => env('PRIVACY_STUDENT_URL'),
        'tutor_url' => env('TERMS_TUTOR_URL'),
        'tutor_privacy_url' => env('PRIVACY_TUTOR_URL'),
        'version' => env('TERMS_VERSION', 'current'),
        'allow_local_placeholder' => env('TERMS_ALLOW_LOCAL_PLACEHOLDER', false),
        'local_placeholder_url' => 'https://www.adobe.com/in/legal/subscription-terms.html',
    ],

    'dashboard' => [
        'student_url' => env('STUDENT_DASHBOARD_URL', env('WHATSAPP_ONBOARDING_DASHBOARD_STUDENT_URL')),
        'tutor_url' => env('TUTOR_DASHBOARD_URL', env('WHATSAPP_ONBOARDING_DASHBOARD_TUTOR_URL')),
        'login_url' => env('WHATSAPP_ONBOARDING_LOGIN_URL'),
        'secure_link_ttl_minutes' => (int) env('WHATSAPP_ONBOARDING_SECURE_LINK_TTL_MINUTES', 30),
        'magic_login_enabled' => env('DASHBOARD_MAGIC_LOGIN_ENABLED', false),
    ],

    'profile' => [
        'signup_enabled' => env('WHATSAPP_SIGNUP_ENABLED', true),
        'student_signup_enabled' => env('WHATSAPP_STUDENT_SIGNUP_ENABLED', true),
        'tutor_signup_enabled' => env('WHATSAPP_TUTOR_SIGNUP_ENABLED', true),
        'create_real_profile' => env('WHATSAPP_CREATE_REAL_PROFILE', ! env('APP_ENV') || env('APP_ENV') === 'production'),
        'student_status' => env('WHATSAPP_STUDENT_STATUS', 'active'),
        'tutor_status' => env('WHATSAPP_TUTOR_STATUS', 'pending_review'),
        'tutor_documents_require_review' => env('WHATSAPP_TUTOR_DOCUMENTS_REQUIRE_REVIEW', true),
        'user_id_prefix_student' => env('WHATSAPP_USER_ID_PREFIX_STUDENT', 'NXS'),
        'user_id_prefix_tutor' => env('WHATSAPP_USER_ID_PREFIX_TUTOR', 'NXT'),
        'user_id_random_length' => (int) env('WHATSAPP_USER_ID_RANDOM_LENGTH', 6),
        'app_version' => env('WHATSAPP_ONBOARDING_APP_VERSION', 'local'),
        'flow_version' => env('WHATSAPP_ONBOARDING_FLOW_VERSION', '2026-01'),
        'state_machine_version' => env('WHATSAPP_ONBOARDING_STATE_MACHINE_VERSION', '2026-01'),
        'message_template_version' => env('WHATSAPP_ONBOARDING_MESSAGE_TEMPLATE_VERSION', '2026-01'),
    ],

    'aws' => [
        'region' => env('AWS_REGION', 'ap-south-1'),
        'secrets_manager_prefix' => env('AWS_SECRETS_MANAGER_PREFIX', '/nxtutors/whatsapp-onboarding'),
        'sqs_queue_url' => env('WHATSAPP_ONBOARDING_SQS_QUEUE_URL'),
        's3_media_bucket' => env('AWS_S3_MEDIA_BUCKET', env('WHATSAPP_ONBOARDING_S3_MEDIA_BUCKET')),
        'eventbridge_bus' => env('WHATSAPP_ONBOARDING_EVENTBRIDGE_BUS'),
    ],

    'media' => [
        'storage_driver' => env('MEDIA_STORAGE_DRIVER', 'local'),
        's3_bucket' => env('AWS_S3_MEDIA_BUCKET', env('WHATSAPP_ONBOARDING_S3_MEDIA_BUCKET')),
        's3_prefix' => env('AWS_S3_MEDIA_PREFIX', 'nxtutors/onboarding'),
        'local_path' => env('WHATSAPP_ONBOARDING_LOCAL_MEDIA_PATH', 'nxtutors/onboarding'),
        'max_kb' => (int) env('WHATSAPP_ONBOARDING_MEDIA_MAX_KB', 2048),
        'allowed_image_mimes' => ['image/jpeg', 'image/png', 'image/jpg', 'image/gif'],
        'degree_allows_pdf' => env('WHATSAPP_ONBOARDING_DEGREE_ALLOWS_PDF', true),
    ],

    'analytics' => [
        'disk' => env('WHATSAPP_ONBOARDING_ANALYTICS_DISK', 's3'),
        's3_bucket' => env('WHATSAPP_ONBOARDING_ANALYTICS_BUCKET', env('AWS_S3_MEDIA_BUCKET')),
        'prefix' => env('WHATSAPP_ONBOARDING_ANALYTICS_PREFIX', 'nxtutors/onboarding_events'),
    ],
];
