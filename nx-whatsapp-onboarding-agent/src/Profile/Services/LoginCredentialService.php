<?php

declare(strict_types=1);

namespace NxTutors\WhatsAppOnboarding\Profile\Services;

use Illuminate\Support\Facades\Hash;

final class LoginCredentialService
{
    /** @return array{temporary_password:string, password_hash:string} */
    public function generateTemporaryPassword(): array
    {
        $length = max(12, (int) config('whatsapp_onboarding_security.password.temporary_length', 18));
        $password = substr(strtr(base64_encode(random_bytes(32)), '+/', '-_'), 0, $length);

        return [
            'temporary_password' => $password,
            'password_hash' => Hash::make($password),
        ];
    }
}
