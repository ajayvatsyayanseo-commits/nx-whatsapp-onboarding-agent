<?php

declare(strict_types=1);

namespace NxTutors\WhatsAppOnboarding\Conversation\Services;

use NxTutors\WhatsAppOnboarding\Contracts\InputExtractorInterface;

final class NoopInputExtractor implements InputExtractorInterface
{
    public function extract(string $input, string $expectedField): array
    {
        return [
            'field' => $expectedField,
            'value' => $input,
            'confidence' => 1.0,
        ];
    }
}
