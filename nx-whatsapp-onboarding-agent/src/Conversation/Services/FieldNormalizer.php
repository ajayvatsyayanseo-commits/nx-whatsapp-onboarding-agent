<?php

declare(strict_types=1);

namespace NxTutors\WhatsAppOnboarding\Conversation\Services;

final class FieldNormalizer
{
    public function normalize(string $field, string $value): string
    {
        $value = trim(preg_replace('/\s+/', ' ', $value) ?? $value);

        return match ($field) {
            'email' => mb_strtolower($value),
            'gender' => mb_strtolower($value),
            'phone' => $this->phone($value),
            'pincode' => preg_replace('/\D+/', '', $value) ?? $value,
            'document_type' => ucwords(mb_strtolower($value)),
            'document_number' => strtoupper(str_replace(' ', '', $value)),
            default => $value,
        };
    }

    public function phone(string $value): string
    {
        $digits = preg_replace('/\D+/', '', $value) ?? $value;
        if (strlen($digits) === 10) {
            return '+91' . $digits;
        }

        if (str_starts_with($digits, '91') && strlen($digits) === 12) {
            return '+' . $digits;
        }

        return str_starts_with($value, '+') ? $value : '+' . $digits;
    }
}
