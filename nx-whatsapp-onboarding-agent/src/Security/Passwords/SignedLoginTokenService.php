<?php

declare(strict_types=1);

namespace NxTutors\WhatsAppOnboarding\Security\Passwords;

final class SignedLoginTokenService
{
    public function issue(string $userId, string $role): string
    {
        $expiresAt = time() + ((int) config('whatsapp_onboarding.dashboard.secure_link_ttl_minutes', 30) * 60);
        $payload = base64_encode(json_encode([
            'sub' => $userId,
            'role' => $role,
            'exp' => $expiresAt,
            'nonce' => bin2hex(random_bytes(8)),
        ], JSON_THROW_ON_ERROR));
        $signature = hash_hmac('sha256', $payload, (string) config('app.key', 'local-testing-key'));

        return $payload . '.' . $signature;
    }

    public function verify(string $token): bool
    {
        [$payload, $signature] = array_pad(explode('.', $token, 2), 2, '');
        if ($payload === '' || $signature === '') {
            return false;
        }

        if (! hash_equals(hash_hmac('sha256', $payload, (string) config('app.key', 'local-testing-key')), $signature)) {
            return false;
        }

        $data = json_decode((string) base64_decode($payload, true), true);

        return is_array($data) && (int) ($data['exp'] ?? 0) >= time();
    }
}
