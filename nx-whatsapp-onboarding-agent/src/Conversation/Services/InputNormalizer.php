<?php

declare(strict_types=1);

namespace NxTutors\WhatsAppOnboarding\Conversation\Services;

final class InputNormalizer
{
    public function normalize(?string $input): string
    {
        $input = trim((string) $input);
        $input = preg_replace('/\s+/', ' ', $input) ?? '';

        return mb_strtolower($input);
    }
}
