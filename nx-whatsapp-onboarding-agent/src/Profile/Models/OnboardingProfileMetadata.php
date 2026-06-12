<?php

declare(strict_types=1);

namespace NxTutors\WhatsAppOnboarding\Profile\Models;

use Illuminate\Database\Eloquent\Model;

final class OnboardingProfileMetadata extends Model
{
    protected $table = 'onboarding_profile_metadata';

    protected $fillable = [
        'onboarding_conversation_id',
        'register_user_id',
        'register_phone_hash',
        'role',
        'force_password_reset',
        'dry_run',
        'metadata',
        'sensitive_purged_at',
    ];

    protected $casts = [
        'force_password_reset' => 'boolean',
        'dry_run' => 'boolean',
        'metadata' => 'array',
        'sensitive_purged_at' => 'datetime',
    ];
}
