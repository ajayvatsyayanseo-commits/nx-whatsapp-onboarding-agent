<?php

declare(strict_types=1);

namespace NxTutors\WhatsAppOnboarding\Common\Support;

final class PhoneNormalizer
{
    /** @return list<string> */
    public function variants(string $phone): array
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';
        $last10 = strlen($digits) >= 10 ? substr($digits, -10) : $digits;

        return array_values(array_unique(array_filter([
            $phone,
            $digits,
            $last10,
            $last10 !== '' ? '91' . $last10 : null,
            $last10 !== '' ? '+91' . $last10 : null,
        ])));
    }

    public function forStorage(string $phone): string
    {
        $format = (string) config('whatsapp_onboarding.nxtutors_legacy.phone_storage_format', 'digits10_or_country_digits');
        $digits = preg_replace('/\D+/', '', $phone) ?? $phone;

        if ($format === 'last10') {
            return strlen($digits) >= 10 ? substr($digits, -10) : $digits;
        }

        if ($format === 'e164_india') {
            $last10 = strlen($digits) >= 10 ? substr($digits, -10) : $digits;
            return '+91' . $last10;
        }

        return $digits;
    }
}
