<?php

declare(strict_types=1);

namespace NxTutors\WhatsAppOnboarding\Profile\Models;

use Illuminate\Database\Eloquent\Model;

final class OnboardingEvent extends Model
{
    protected $table = 'onboarding_events';

    protected $fillable = [
        'onboarding_conversation_id',
        'wa_message_id',
        'wa_phone',
        'idempotency_key',
        'direction',
        'event_type',
        'payload',
        'status',
        'webhook_timestamp',
        'received_at',
        'processed_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'webhook_timestamp' => 'datetime',
        'received_at' => 'datetime',
        'processed_at' => 'datetime',
    ];
}
