<?php

declare(strict_types=1);

namespace NxTutors\WhatsAppOnboarding\Queue\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use NxTutors\WhatsAppOnboarding\Contracts\MediaStorageInterface;

final class DownloadWhatsAppMediaJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;
    public int $backoff = 20;

    public function __construct(
        public readonly string $mediaId,
        public readonly string $purpose,
    ) {
    }

    public function handle(MediaStorageInterface $media): void
    {
        $media->storeFromWhatsAppMediaId($this->mediaId, $this->purpose);
    }
}
