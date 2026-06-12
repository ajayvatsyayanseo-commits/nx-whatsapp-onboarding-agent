<?php

declare(strict_types=1);

namespace NxTutors\WhatsAppOnboarding\Security\PiiMasking;

final readonly class PiiMaskingLogProcessor
{
    public function __construct(private PiiMasker $masker)
    {
    }

    /** @param array<string, mixed> $record */
    public function __invoke(array $record): array
    {
        if (! (bool) config('whatsapp_onboarding_security.pii_masking.enabled', true)) {
            return $record;
        }

        if (isset($record['context']) && is_array($record['context'])) {
            $record['context'] = $this->masker->maskArray($record['context']);
        }

        if (isset($record['extra']) && is_array($record['extra'])) {
            $record['extra'] = $this->masker->maskArray($record['extra']);
        }

        if (isset($record['message'])) {
            $record['message'] = $this->masker->maskValue('message', (string) $record['message']);
        }

        return $record;
    }
}
