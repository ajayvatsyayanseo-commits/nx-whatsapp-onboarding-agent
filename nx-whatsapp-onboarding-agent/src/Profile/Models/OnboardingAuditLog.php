<?php

declare(strict_types=1);

namespace NxTutors\WhatsAppOnboarding\Profile\Models;

use Illuminate\Database\Eloquent\Model;

final class OnboardingAuditLog extends Model
{
    protected $table = 'onboarding_audit_logs';

    protected $fillable = [
        'onboarding_conversation_id',
        'action',
        'actor',
        'masked_metadata',
        'created_at',
    ];

    public const UPDATED_AT = null;

    protected $casts = [
        'masked_metadata' => 'array',
        'created_at' => 'datetime',
    ];
}
