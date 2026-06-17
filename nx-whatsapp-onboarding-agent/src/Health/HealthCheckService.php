<?php

declare(strict_types=1);

namespace NxTutors\WhatsAppOnboarding\Health;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

final class HealthCheckService
{
    /** @return array<string, mixed> */
    public function live(): array
    {
        return ['ok' => true, 'service' => 'nx-whatsapp-onboarding'];
    }

    /** @return array<string, mixed> */
    public function ready(): array
    {
        return [
            'ok' => $this->db() && $this->redis(),
            'checks' => [
                'db' => $this->db(),
                'redis' => $this->redis(),
                'queue' => $this->queue(),
            ],
        ];
    }

    /** @return array<string, mixed> */
    public function deep(): array
    {
        $checks = [
            'db' => $this->db(),
            'redis' => $this->redis(),
            'queue' => $this->queue(),
            's3' => $this->s3(),
            'meta_configured' => (string) config('whatsapp_onboarding.meta.phone_number_id', '') !== '',
        ];

        return ['ok' => ! in_array(false, $checks, true), 'checks' => $checks];
    }

    /** @return array<string, mixed> */
    public function dbStatus(): array
    {
        $ok = $this->db();

        return ['ok' => $ok, 'check' => 'db'];
    }

    /**
     * WhatsApp/Meta configuration health. This agent does not have to send
     * directly (lead-intake sends replies), so direct-send readiness is
     * reported separately from genuine Meta-webhook verification readiness.
     *
     * @return array<string, mixed>
     */
    public function whatsappStatus(): array
    {
        $appSecret = (string) config('whatsapp_onboarding.meta.app_secret', '') !== '';
        $accessToken = (string) config('whatsapp_onboarding.meta.access_token', '') !== '';
        $phoneNumberId = (string) config('whatsapp_onboarding.meta.phone_number_id', '') !== '';
        $verifyToken = (string) config('whatsapp_onboarding.meta.verify_token', '') !== '';

        return [
            'ok' => $appSecret || ($accessToken && $phoneNumberId),
            'app_secret_configured' => $appSecret,
            'access_token_configured' => $accessToken,
            'phone_number_id_configured' => $phoneNumberId,
            'verify_token_configured' => $verifyToken,
            'direct_send_ready' => $accessToken && $phoneNumberId,
        ];
    }

    /**
     * Internal handoff health. Shows whether the shared secret is configured
     * and whether the handoff route is enabled.
     *
     * @return array<string, mixed>
     */
    public function internalHandoffStatus(): array
    {
        $secretConfigured = (string) config('whatsapp_onboarding.internal_handoff.secret', '') !== '';
        $routeEnabled = (bool) config('whatsapp_onboarding.internal_handoff.enabled', true);

        return [
            'ok' => $secretConfigured && $routeEnabled,
            'onboarding_agent_internal_secret_configured' => $secretConfigured,
            'handoff_route_enabled' => $routeEnabled,
        ];
    }

    private function db(): bool
    {
        try {
            DB::select('select 1');
            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    private function redis(): bool
    {
        try {
            Cache::put('nxtutors:onboarding:health', true, 5);
            return Cache::get('nxtutors:onboarding:health') === true;
        } catch (\Throwable) {
            return false;
        }
    }

    private function queue(): bool
    {
        return Queue::getDefaultDriver() !== '';
    }

    private function s3(): bool
    {
        if ((string) config('whatsapp_onboarding.media.storage_driver') !== 's3') {
            return true;
        }

        try {
            Storage::disk('s3')->exists('.');
            return true;
        } catch (\Throwable) {
            return false;
        }
    }
}
