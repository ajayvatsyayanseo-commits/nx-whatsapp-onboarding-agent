<?php

declare(strict_types=1);

namespace NxTutors\WhatsAppOnboarding\Common\Support;

final class Arr
{
    /** @param array<string, mixed> $data */
    public static function onlyFilled(array $data): array
    {
        return array_filter($data, static fn (mixed $value): bool => $value !== null && $value !== '');
    }
}
