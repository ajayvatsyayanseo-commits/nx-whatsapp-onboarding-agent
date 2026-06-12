<?php

declare(strict_types=1);

namespace NxTutors\WhatsAppOnboarding\Observability\Notifications;

use NxTutors\WhatsAppOnboarding\Contracts\AdminNotificationInterface;
use NxTutors\WhatsAppOnboarding\Profile\Models\HumanHandoffTicket;

final readonly class CompositeAdminNotifier implements AdminNotificationInterface
{
    /** @param iterable<AdminNotificationInterface> $notifiers */
    public function __construct(private iterable $notifiers = [])
    {
    }

    public function notifyHumanHandoff(HumanHandoffTicket $ticket): void
    {
        foreach ($this->notifiers as $notifier) {
            $notifier->notifyHumanHandoff($ticket);
        }
    }
}
