<?php

declare(strict_types=1);

namespace NxTutors\WhatsAppOnboarding\Contracts;

use NxTutors\WhatsAppOnboarding\Profile\Models\HumanHandoffTicket;

interface AdminNotificationInterface
{
    public function notifyHumanHandoff(HumanHandoffTicket $ticket): void;
}
