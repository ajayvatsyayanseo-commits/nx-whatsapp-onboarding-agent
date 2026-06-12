<?php

declare(strict_types=1);

namespace NxTutors\WhatsAppOnboarding\Profile\Models;

use Illuminate\Database\Eloquent\Model;

final class HumanHandoffTicket extends Model
{
    protected $table = 'human_handoff_tickets';

    protected $fillable = [
        'onboarding_conversation_id',
        'wa_phone',
        'role',
        'reason',
        'reason_code',
        'status',
        'assigned_to',
        'opened_at',
        'closed_at',
    ];

    protected $casts = [
        'opened_at' => 'datetime',
        'closed_at' => 'datetime',
    ];
}
