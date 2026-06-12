<?php

declare(strict_types=1);

namespace NxTutors\WhatsAppOnboarding\WhatsApp\Services;

final class MetaWebhookSignatureVerifier
{
    public function verify(string $rawBody, ?string $signatureHeader): bool
    {
        $secret = (string) config('whatsapp_onboarding.meta.app_secret', '');
        if ($secret === '') {
            return app()->environment(['local', 'testing']);
        }

        if ($signatureHeader === null || ! str_starts_with($signatureHeader, 'sha256=')) {
            return false;
        }

        $expected = 'sha256=' . hash_hmac('sha256', $rawBody, $secret);

        return hash_equals($expected, $signatureHeader);
    }
}
