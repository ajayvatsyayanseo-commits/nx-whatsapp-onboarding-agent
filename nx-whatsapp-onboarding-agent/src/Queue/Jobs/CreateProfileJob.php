<?php

declare(strict_types=1);

namespace NxTutors\WhatsAppOnboarding\Queue\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use NxTutors\WhatsAppOnboarding\Profile\DTO\ProfileCreationCommand;
use NxTutors\WhatsAppOnboarding\Profile\Services\ProfileCreationDispatcher;

final class CreateProfileJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;
    public int $backoff = 15;

    public function __construct(public readonly ProfileCreationCommand $command)
    {
    }

    public function handle(ProfileCreationDispatcher $dispatcher): void
    {
        $dispatcher->dispatchNow($this->command);
    }
}
