<?php

declare(strict_types=1);

namespace NxTutors\WhatsAppOnboarding\Profile\Models;

use Illuminate\Database\Eloquent\Model;

final class OnboardingConversation extends Model
{
    protected $table = 'onboarding_conversations';

    protected $fillable = [
        'wa_phone',
        'role',
        'current_state',
        'status',
        'locale',
        'context',
        'field_attempts',
        'invalid_attempts',
        'lock_version',
        'terms_url',
        'terms_version',
        'terms_role',
        'terms_accepted_message_id',
        'terms_metadata',
        'terms_accepted_at',
        'otp_verified_at',
        'completed_at',
        'last_message_at',
    ];

    protected $casts = [
        'context' => 'array',
        'field_attempts' => 'array',
        'terms_metadata' => 'array',
        'terms_accepted_at' => 'datetime',
        'otp_verified_at' => 'datetime',
        'completed_at' => 'datetime',
        'last_message_at' => 'datetime',
    ];
}
