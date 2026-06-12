<?php

declare(strict_types=1);

namespace NxTutors\WhatsAppOnboarding\Observability\Logging;

use NxTutors\WhatsAppOnboarding\Security\PiiMasking\PiiMasker;

final readonly class StructuredLogContext
{
    public function __construct(private PiiMasker $masker)
    {
    }

    /** @param array<string, mixed> $context */
    public function build(array $context): array
    {
        return $this->masker->maskArray(array_merge([
            'trace_id' => request()?->headers->get('X-Trace-Id') ?: bin2hex(random_bytes(16)),
            'app_version' => config('whatsapp_onboarding.profile.app_version', 'local'),
            'flow_version' => config('whatsapp_onboarding.profile.flow_version', '2026-01'),
            'channel' => 'whatsapp',
        ], $context));
    }
}
