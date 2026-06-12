<?php

declare(strict_types=1);

namespace NxTutors\WhatsAppOnboarding\Security\AbuseDetection;

final class AbuseDetector
{
    public function isSuspicious(string $text): bool
    {
        $normalized = mb_strtolower($text);

        return mb_strlen($text) > 4000
            || str_contains($normalized, 'refund')
            || str_contains($normalized, 'legal')
            || str_contains($normalized, 'unsafe')
            || str_contains($normalized, 'harassment')
            || str_contains($normalized, 'abuse');
    }
}
