<?php

declare(strict_types=1);

namespace NxTutors\WhatsAppOnboarding\Profile\Models;

use Illuminate\Database\Eloquent\Model;

final class OnboardingTermsAcceptance extends Model
{
    protected $table = 'onboarding_terms_acceptances';

    protected $fillable = [
        'onboarding_conversation_id',
        'role',
        'terms_url',
        'terms_version',
        'accepted_at',
        'acceptance_message_id',
        'acceptance_text_hash',
        'user_phone_hash',
        'ip_hash',
        'user_agent_hash',
        'metadata',
    ];

    protected $casts = [
        'accepted_at' => 'datetime',
        'metadata' => 'array',
    ];
}
