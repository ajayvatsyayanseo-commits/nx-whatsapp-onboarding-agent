<?php

declare(strict_types=1);

return [
    'enabled' => env('WHATSAPP_ONBOARDING_ENABLED', true),
    'route_prefix' => env('WHATSAPP_ONBOARDING_ROUTE_PREFIX', 'whatsapp/onboarding'),
    'queue' => env('WHATSAPP_ONBOARDING_QUEUE', 'whatsapp-onboarding'),
    'database_connection' => env('WHATSAPP_ONBOARDING_DB_CONNECTION', env('DB_CONNECTION', 'mysql')),
    'redis_connection' => env('WHATSAPP_ONBOARDING_REDIS_CONNECTION', env('REDIS_CONNECTION', 'default')),

    // Internal handoff from the lead-intake agent.
    //
    // The lead-intake agent owns the public Meta webhook for the shared WhatsApp
    // number. It forwards signup/onboarding messages to this agent over an
    // internal HTTP call authenticated with a shared secret instead of a Meta
    // X-Hub-Signature-256 header. Secrets MUST be read through config (not env()
    // at call time) so they survive `php artisan config:cache` in production.
    'internal_handoff' => [
        'enabled' => env('ONBOARDING_HANDOFF_ENABLED', true),
        'secret' => env('ONBOARDING_AGENT_INTERNAL_SECRET'),
        'header' => 'X-NXTUTORS-INTERNAL-SECRET',
        'source' => env('ONBOARDING_HANDOFF_SOURCE', 'lead_intake_agent'),
    ],

    'meta' => [
        'verify_token' => env('META_WHATSAPP_VERIFY_TOKEN'),
        'app_secret' => env('META_WHATSAPP_APP_SECRET', env('META_APP_SECRET')),
        'access_token' => env('META_WHATSAPP_ACCESS_TOKEN', env('META_ACCESS_TOKEN')),
        'phone_number_id' => env('META_WHATSAPP_PHONE_NUMBER_ID', env('META_PHONE_NUMBER_ID')),
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
        'student_url' => env('TERMS_STUDENT_URL', 'https://www.nxtutors.com/terms-conditions'),
        'student_privacy_url' => env('PRIVACY_STUDENT_URL', 'https://www.nxtutors.com/privacy-policy'),
        'tutor_url' => env('TERMS_TUTOR_URL', 'https://www.nxtutors.com/terms-conditions'),
        'tutor_privacy_url' => env('PRIVACY_TUTOR_URL', 'https://www.nxtutors.com/privacy-policy'),
        'version' => env('TERMS_VERSION', 'current'),
        'allow_local_placeholder' => env('TERMS_ALLOW_LOCAL_PLACEHOLDER', false),
        'local_placeholder_url' => 'https://www.adobe.com/in/legal/subscription-terms.html',
    ],

    'dashboard' => [
        'student_url' => env('STUDENT_DASHBOARD_URL', env('WHATSAPP_ONBOARDING_DASHBOARD_STUDENT_URL', 'https://www.nxtutors.com/user/dashboard')),
        'tutor_url' => env('TUTOR_DASHBOARD_URL', env('WHATSAPP_ONBOARDING_DASHBOARD_TUTOR_URL', 'https://www.nxtutors.com/teacher/dashboard')),
        'login_url' => env('WHATSAPP_ONBOARDING_LOGIN_URL', 'https://www.nxtutors.com/login'),
        'student_change_password_url' => env('STUDENT_CHANGE_PASSWORD_URL', 'https://www.nxtutors.com/user/change-password'),
        'tutor_change_password_url' => env('TUTOR_CHANGE_PASSWORD_URL', 'https://www.nxtutors.com/teacher/change-password'),
        'secure_link_ttl_minutes' => (int) env('WHATSAPP_ONBOARDING_SECURE_LINK_TTL_MINUTES', 30),
        'magic_login_enabled' => env('DASHBOARD_MAGIC_LOGIN_ENABLED', false),
    ],

    'profile' => [
        'signup_enabled' => env('WHATSAPP_SIGNUP_ENABLED', true),
        'student_signup_enabled' => env('WHATSAPP_STUDENT_SIGNUP_ENABLED', true),
        'tutor_signup_enabled' => env('WHATSAPP_TUTOR_SIGNUP_ENABLED', true),
        'create_real_profile' => env('WHATSAPP_CREATE_REAL_PROFILE', ! env('APP_ENV') || env('APP_ENV') === 'production'),
        'student_status' => env('WHATSAPP_STUDENT_STATUS', env('NXTUTORS_STATUS_ACTIVE_VALUE', 't')),
        'tutor_status' => env('WHATSAPP_TUTOR_STATUS', env('NXTUTORS_STATUS_ACTIVE_VALUE', 't')),
        'otp_status_verified' => env('WHATSAPP_OTP_STATUS_VERIFIED', env('NXTUTORS_OTP_VERIFIED_VALUE', 't')),
        'tutor_documents_require_review' => env('WHATSAPP_TUTOR_DOCUMENTS_REQUIRE_REVIEW', false),
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
        'storage_driver' => env('MEDIA_STORAGE_DRIVER', 'legacy_public_user'),
        's3_bucket' => env('AWS_S3_MEDIA_BUCKET', env('WHATSAPP_ONBOARDING_S3_MEDIA_BUCKET')),
        's3_prefix' => env('AWS_S3_MEDIA_PREFIX', 'nxtutors/onboarding'),
        'local_path' => env('WHATSAPP_ONBOARDING_LOCAL_MEDIA_PATH', 'storage/user'),
        'db_value' => env('WHATSAPP_ONBOARDING_MEDIA_DB_VALUE', 'filename_only'),
        'max_kb' => (int) env('WHATSAPP_ONBOARDING_MEDIA_MAX_KB', 2048),
        'allowed_image_mimes' => ['image/jpeg', 'image/png', 'image/jpg', 'image/gif'],
        'degree_allows_pdf' => env('WHATSAPP_ONBOARDING_DEGREE_ALLOWS_PDF', true),
    ],

    'analytics' => [
        'disk' => env('WHATSAPP_ONBOARDING_ANALYTICS_DISK', 's3'),
        's3_bucket' => env('WHATSAPP_ONBOARDING_ANALYTICS_BUCKET', env('AWS_S3_MEDIA_BUCKET')),
        'prefix' => env('WHATSAPP_ONBOARDING_ANALYTICS_PREFIX', 'nxtutors/onboarding_events'),
    ],

    'nxtutors_legacy' => [
        'enabled' => env('NXTUTORS_LEGACY_WEBSITE_MODE', true),
        'login_identifier' => env('NXTUTORS_LOGIN_IDENTIFIER', 'email'),
        'student_join_as' => env('NXTUTORS_STUDENT_JOIN_AS', 'student'),
        'tutor_join_as' => env('NXTUTORS_TUTOR_JOIN_AS', 'teacher'),
        'student_user_type' => env('NXTUTORS_STUDENT_USER_TYPE', 'student'),
        'tutor_user_type' => env('NXTUTORS_TUTOR_USER_TYPE', 'Individual'),
        'status_active_value' => env('NXTUTORS_STATUS_ACTIVE_VALUE', 't'),
        'otp_verified_value' => env('NXTUTORS_OTP_VERIFIED_VALUE', 't'),
        'phone_storage_format' => env('NXTUTORS_PHONE_STORAGE_FORMAT', 'digits10_or_country_digits'),
        'user_id_mode' => env('NXTUTORS_USER_ID_MODE', 'legacy_numeric'),
        'mysql_user_id_lock_name' => env('NXTUTORS_MYSQL_USER_ID_LOCK_NAME', 'nxtutors_register_user_id_generation'),
        'user_id_max_retries' => (int) env('NXTUTORS_USER_ID_MAX_RETRIES', 5),
        'store_c_password' => env('NXTUTORS_STORE_C_PASSWORD', false),
        'force_password_reset_if_column_exists' => env('NXTUTORS_FORCE_PASSWORD_RESET_IF_COLUMN_EXISTS', true),
        'tutor_require_documents_before_create' => env('NXTUTORS_TUTOR_REQUIRE_DOCUMENTS_BEFORE_CREATE', false),
        'whatsapp_phone_number' => env('WHATSAPP_PHONE_NUMBER', '917836034313'),
    ],
];
