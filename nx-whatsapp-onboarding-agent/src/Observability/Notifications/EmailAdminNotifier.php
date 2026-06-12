<?php

declare(strict_types=1);

namespace NxTutors\WhatsAppOnboarding\Observability\Notifications;

use NxTutors\WhatsAppOnboarding\Contracts\AdminNotificationInterface;
use NxTutors\WhatsAppOnboarding\Profile\Models\HumanHandoffTicket;

final class EmailAdminNotifier implements AdminNotificationInterface
{
    public function notifyHumanHandoff(HumanHandoffTicket $ticket): void
    {
        // Wire to Laravel Mail in the host application when support inbox details are known.
    }
}
