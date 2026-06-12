<?php

declare(strict_types=1);

namespace NxTutors\WhatsAppOnboarding\Conversation\Services;

use NxTutors\WhatsAppOnboarding\Security\PiiMasking\PiiMasker;

final readonly class ReviewSummaryBuilder
{
    public function __construct(private PiiMasker $masker)
    {
    }

    /** @param array<string, mixed> $context */
    public function build(array $context): string
    {
        $lines = [__('nx-whatsapp-onboarding::common.review_title')];
        foreach ($context as $key => $value) {
            if (str_starts_with((string) $key, '_') || in_array($key, ['otp_hash', 'otp_issued_at', 'otp_attempts', 'temporary_password'], true)) {
                continue;
            }

            $label = str_replace('_', ' ', ucfirst($key));
            $lines[] = "{$label}: " . $this->masker->maskValue($key, (string) $value);
        }

        $lines[] = __('nx-whatsapp-onboarding::common.review_footer');

        return implode("\n", $lines);
    }
}
