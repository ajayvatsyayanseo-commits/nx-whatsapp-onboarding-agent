<?php

declare(strict_types=1);

namespace NxTutors\WhatsAppOnboarding\Profile\Services;

use NxTutors\WhatsAppOnboarding\Profile\Models\OnboardingConversation;
use NxTutors\WhatsAppOnboarding\Profile\Models\OnboardingProfileMetadata;

final class ProfileMetadataService
{
    /** @param array<string, mixed> $metadata */
    public function record(OnboardingConversation $conversation, ?string $registerUserId, bool $dryRun, array $metadata = []): void
    {
        OnboardingProfileMetadata::query()->create([
            'onboarding_conversation_id' => $conversation->id,
            'register_user_id' => $registerUserId,
            'register_phone_hash' => $this->hash((string) $conversation->wa_phone),
            'role' => (string) $conversation->role,
            'force_password_reset' => true,
            'dry_run' => $dryRun,
            'metadata' => $metadata,
        ]);
    }

    public function purgeSensitiveDraft(OnboardingConversation $conversation): void
    {
        $context = $conversation->context ?? [];
        foreach (['document_number', 'otp_hash', 'otp_issued_at', 'otp_attempts'] as $key) {
            unset($context[$key]);
        }

        $conversation->forceFill(['context' => $context])->save();

        OnboardingProfileMetadata::query()
            ->where('onboarding_conversation_id', $conversation->id)
            ->update(['sensitive_purged_at' => now()]);
    }

    private function hash(string $value): string
    {
        return hash_hmac('sha256', $value, (string) config('app.key', 'local-testing-key'));
    }
}
