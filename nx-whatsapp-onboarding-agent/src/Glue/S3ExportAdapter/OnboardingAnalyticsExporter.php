<?php

declare(strict_types=1);

namespace NxTutors\WhatsAppOnboarding\Glue\S3ExportAdapter;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use NxTutors\WhatsAppOnboarding\Profile\Models\OnboardingEvent;

final class OnboardingAnalyticsExporter
{
    public function exportForDate(Carbon $date): string
    {
        $rows = OnboardingEvent::query()
            ->select(['id', 'onboarding_conversation_id', 'direction', 'event_type', 'status', 'created_at', 'processed_at', 'payload'])
            ->whereDate('created_at', $date->toDateString())
            ->orderBy('id')
            ->cursor()
            ->map(fn (OnboardingEvent $event): string => json_encode($this->sanitize($event), JSON_THROW_ON_ERROR))
            ->implode("\n");

        $key = sprintf(
            '%s/year=%s/month=%s/day=%s/events.json',
            trim((string) config('whatsapp_onboarding.analytics.prefix', 'nxtutors/onboarding_events'), '/'),
            $date->format('Y'),
            $date->format('m'),
            $date->format('d'),
        );

        Storage::disk((string) config('whatsapp_onboarding.analytics.disk', 's3'))->put($key, $rows . "\n");

        return $key;
    }

    /** @return array<string, mixed> */
    private function sanitize(OnboardingEvent $event): array
    {
        $payload = $event->payload ?? [];
        $conversationId = (string) ($event->onboarding_conversation_id ?? '');

        return [
            'event_id' => $event->id,
            'conversation_id_hash' => $conversationId !== '' ? hash('sha256', $conversationId) : null,
            'role' => $payload['role'] ?? null,
            'state' => $payload['state'] ?? null,
            'event_type' => $event->event_type,
            'error_code' => $payload['error_code'] ?? null,
            'latency_ms' => $event->processed_at && $event->created_at ? $event->processed_at->diffInMilliseconds($event->created_at) : null,
            'created_at' => optional($event->created_at)->toIso8601String(),
            'app_version' => config('whatsapp_onboarding.profile.app_version', 'local'),
            'flow_version' => config('whatsapp_onboarding.profile.flow_version', '2026-01'),
            'channel' => 'whatsapp',
        ];
    }
}
