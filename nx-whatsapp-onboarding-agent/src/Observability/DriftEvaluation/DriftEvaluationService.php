<?php

declare(strict_types=1);

namespace NxTutors\WhatsAppOnboarding\Observability\DriftEvaluation;

use Illuminate\Support\Facades\File;
use NxTutors\WhatsAppOnboarding\Profile\Models\HumanHandoffTicket;
use NxTutors\WhatsAppOnboarding\Profile\Models\OnboardingConversation;
use NxTutors\WhatsAppOnboarding\Profile\Models\OnboardingEvent;

final class DriftEvaluationService
{
    /** @return array<string, mixed> */
    public function evaluate(): array
    {
        $started = $this->safeCount(fn (): int => OnboardingConversation::query()->where('created_at', '>=', now()->subWeek())->count());
        $completed = $this->safeCount(fn (): int => OnboardingConversation::query()->where('completed_at', '>=', now()->subWeek())->count());
        $handoffs = $this->safeCount(fn (): int => HumanHandoffTicket::query()->where('opened_at', '>=', now()->subWeek())->count());
        $metaFailures = $this->safeCount(fn (): int => OnboardingEvent::query()->where('event_type', 'like', '%error%')->where('created_at', '>=', now()->subWeek())->count());
        $completionRate = $started > 0 ? round(($completed / $started) * 100, 2) : 0.0;
        $handoffRate = $started > 0 ? round(($handoffs / $started) * 100, 2) : 0.0;

        $report = [
            'generated_at' => now()->toIso8601String(),
            'window_days' => 7,
            'metrics' => [
                'signup_starts' => $started,
                'completions' => $completed,
                'completion_rate' => $completionRate,
                'human_handoff_rate' => $handoffRate,
                'meta_send_failures' => $metaFailures,
            ],
            'alerts' => $this->alerts($completionRate, $handoffRate, $metaFailures),
        ];

        File::ensureDirectoryExists(storage_path('reports'));
        File::put(storage_path('reports/onboarding_drift_report.json'), json_encode($report, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));

        return $report;
    }

    /** @param callable():int $callback */
    private function safeCount(callable $callback): int
    {
        try {
            return $callback();
        } catch (\Throwable) {
            return 0;
        }
    }

    /** @return list<string> */
    private function alerts(float $completionRate, float $handoffRate, int $metaFailures): array
    {
        $alerts = [];
        if ($completionRate < (float) config('whatsapp_onboarding_observability.drift.min_completion_rate_percent', 40)) {
            $alerts[] = 'COMPLETION_RATE_DROP';
        }

        if ($handoffRate > (float) config('whatsapp_onboarding_observability.drift.max_handoff_rate_percent', 25)) {
            $alerts[] = 'HANDOFF_RATE_SPIKE';
        }

        if ($metaFailures > (int) config('whatsapp_onboarding_observability.drift.max_meta_failures', 20)) {
            $alerts[] = 'META_SEND_FAILURE_SPIKE';
        }

        return $alerts;
    }
}
