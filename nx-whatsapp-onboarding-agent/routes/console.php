<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;

Artisan::command('nx-whatsapp-onboarding:config-check', function (): int {
    app(\NxTutors\WhatsAppOnboarding\Contracts\PolicyGuardInterface::class)->assertSafeConfiguration();
    $this->info('NXtutors WhatsApp onboarding configuration is valid.');

    return self::SUCCESS;
})->purpose('Validate WhatsApp onboarding production configuration.');

Artisan::command('nx-whatsapp-onboarding:retention-cleanup', function (): int {
    $service = app(\NxTutors\WhatsAppOnboarding\Profile\Services\RetentionPolicyService::class);
    $expired = $service->expireOldDrafts();
    $purged = $service->purgeOldRawWebhookPayloads();
    $this->info("Expired drafts: {$expired}; purged webhook payloads: {$purged}");

    return self::SUCCESS;
})->purpose('Apply WhatsApp onboarding retention policy.');

Artisan::command('nxtutors:onboarding:evaluate-drift', function (): int {
    $report = app(\NxTutors\WhatsAppOnboarding\Observability\DriftEvaluation\DriftEvaluationService::class)->evaluate();
    $this->info('Drift report generated at storage/reports/onboarding_drift_report.json');
    $this->line(json_encode($report['alerts'], JSON_THROW_ON_ERROR));

    return self::SUCCESS;
})->purpose('Evaluate onboarding funnel drift and write a JSON report.');

Artisan::command('nxtutors:onboarding:pause {--reason=Manual pause}', function (): int {
    Cache::forever('nxtutors:onboarding:paused', true);
    Cache::forever('nxtutors:onboarding:pause_reason', (string) $this->option('reason'));
    $this->info('NXtutors WhatsApp onboarding paused.');

    return self::SUCCESS;
})->purpose('Pause WhatsApp onboarding through a runtime cache switch.');

Artisan::command('nxtutors:onboarding:resume', function (): int {
    Cache::forget('nxtutors:onboarding:paused');
    Cache::forget('nxtutors:onboarding:pause_reason');
    $this->info('NXtutors WhatsApp onboarding resumed.');

    return self::SUCCESS;
})->purpose('Resume WhatsApp onboarding.');

Artisan::command('nxtutors:onboarding:export-analytics {date?}', function (?string $date = null): int {
    $day = $date ? \Illuminate\Support\Carbon::parse($date) : now()->subDay();
    $key = app(\NxTutors\WhatsAppOnboarding\Glue\S3ExportAdapter\OnboardingAnalyticsExporter::class)->exportForDate($day);
    $this->info("Exported sanitized onboarding analytics to {$key}");

    return self::SUCCESS;
})->purpose('Export sanitized onboarding events to S3 for Glue/Athena.');

Artisan::command('nxtutors:onboarding:privacy-delete {phone}', function (string $phone): int {
    $count = app(\NxTutors\WhatsAppOnboarding\Privacy\DataDeletionService::class)->anonymizePhone($phone);
    $this->info("Anonymized onboarding data for {$count} conversations.");

    return self::SUCCESS;
})->purpose('Anonymize onboarding data for privacy deletion requests.');
