<?php

declare(strict_types=1);

namespace NxTutors\WhatsAppOnboarding\Queue\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use NxTutors\WhatsAppOnboarding\Glue\S3ExportAdapter\OnboardingAnalyticsExporter;

final class ExportAnalyticsJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 2;
    public int $backoff = 60;

    public function handle(OnboardingAnalyticsExporter $exporter): void
    {
        $exporter->exportForDate(now()->subDay());
    }
}
