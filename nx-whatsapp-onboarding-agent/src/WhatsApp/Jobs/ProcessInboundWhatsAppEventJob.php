<?php

declare(strict_types=1);

namespace NxTutors\WhatsAppOnboarding\WhatsApp\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use NxTutors\WhatsAppOnboarding\Conversation\Services\ConversationOrchestrator;
use NxTutors\WhatsAppOnboarding\Profile\Models\OnboardingEvent;

final class ProcessInboundWhatsAppEventJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;
    public int $backoff = 5;

    public function __construct(public readonly int $eventId)
    {
    }

    public function handle(ConversationOrchestrator $orchestrator): void
    {
        $event = OnboardingEvent::query()->findOrFail($this->eventId);
        $orchestrator->handle($event);
        $event->forceFill(['status' => 'processed', 'processed_at' => now()])->save();
    }
}
