<?php

declare(strict_types=1);

namespace NxTutors\WhatsAppOnboarding\Security\PiiMasking;

final class PiiMasker
{
    public function maskValue(string $key, string $value): string
    {
        $key = mb_strtolower($key);

        if (str_contains($key, 'password') || $key === 'otp') {
            return '[redacted]';
        }

        if (str_contains($key, 'dob') || str_contains($key, 'birth')) {
            return '[masked date]';
        }

        if (str_contains($key, 'address')) {
            return '[masked address]';
        }

        if (in_array($key, ['body', 'text', 'message'], true)) {
            return '[masked message body]';
        }

        if (str_contains($key, 'email')) {
            return preg_replace('/(^.).*(@.*$)/', '$1***$2', $value) ?? '[masked]';
        }

        if (str_contains($key, 'phone')) {
            return $this->maskPhone($value);
        }

        if (str_contains($key, 'document')) {
            return strlen($value) <= 4 ? '****' : str_repeat('*', max(0, strlen($value) - 4)) . substr($value, -4);
        }

        return $value;
    }

    /** @param array<string, mixed> $data */
    public function maskArray(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = $this->maskArray($value);
                continue;
            }

            $data[$key] = $this->maskValue((string) $key, (string) $value);
        }

        return $data;
    }

    private function maskPhone(string $value): string
    {
        $digits = preg_replace('/\D+/', '', $value) ?? '';
        if (strlen($digits) < 6) {
            return '****';
        }

        if (str_starts_with($value, '+') && strlen($digits) > 10) {
            $countryCode = substr($digits, 0, -10);
            return '+' . $countryCode . str_repeat('*', 6) . substr($digits, -4);
        }

        return str_repeat('*', max(0, strlen($digits) - 4)) . substr($digits, -4);
    }
}
