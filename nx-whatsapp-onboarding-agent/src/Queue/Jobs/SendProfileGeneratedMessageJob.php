<?php

declare(strict_types=1);

namespace NxTutors\WhatsAppOnboarding\Queue\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use NxTutors\WhatsAppOnboarding\Profile\Services\ProfileGenerationService;

final class SendProfileGeneratedMessageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        private int $conversationId,
        private string $role,
    ) {
        $this->onQueue('whatsapp-notifications');
        $this->delay(now()->addSeconds(60));
    }

    public function handle(ProfileGenerationService $service): void
    {
        $result = $service->generateProfileAndNotify($this->conversationId, $this->role);

        if (! $result['success']) {
            $this->fail(new \Exception($result['error'] ?? 'Unknown error'));
        }
    }
}
