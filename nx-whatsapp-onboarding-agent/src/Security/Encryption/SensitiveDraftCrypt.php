<?php

declare(strict_types=1);

namespace NxTutors\WhatsAppOnboarding\Security\Encryption;

use Illuminate\Support\Facades\Crypt;

final class SensitiveDraftCrypt
{
    /** @var list<string> */
    private array $fields = ['document_number'];

    public function encryptIfSensitive(string $field, string $value): string
    {
        if (! $this->enabled() || ! in_array($field, $this->fields, true) || str_starts_with($value, 'enc:v1:')) {
            return $value;
        }

        return 'enc:v1:' . Crypt::encryptString($value);
    }

    public function decryptIfNeeded(mixed $value): mixed
    {
        if (! is_string($value) || ! str_starts_with($value, 'enc:v1:')) {
            return $value;
        }

        return Crypt::decryptString(substr($value, strlen('enc:v1:')));
    }

    /** @param array<string, mixed> $context */
    public function decryptContext(array $context): array
    {
        foreach ($context as $key => $value) {
            $context[$key] = $this->decryptIfNeeded($value);
        }

        return $context;
    }

    private function enabled(): bool
    {
        return (bool) config('whatsapp_onboarding_security.draft_encryption.enabled', true) && (string) config('app.key', '') !== '';
    }
}
