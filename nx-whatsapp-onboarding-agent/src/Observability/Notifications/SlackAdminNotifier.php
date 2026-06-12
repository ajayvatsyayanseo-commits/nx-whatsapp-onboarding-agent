<?php

declare(strict_types=1);

namespace NxTutors\WhatsAppOnboarding\Observability\Notifications;

use NxTutors\WhatsAppOnboarding\Contracts\AdminNotificationInterface;
use NxTutors\WhatsAppOnboarding\Profile\Models\HumanHandoffTicket;

final class SlackAdminNotifier implements AdminNotificationInterface
{
    public function notifyHumanHandoff(HumanHandoffTicket $ticket): void
    {
        // Placeholder for Slack webhook integration.
    }
}
