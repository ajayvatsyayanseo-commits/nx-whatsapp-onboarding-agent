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
