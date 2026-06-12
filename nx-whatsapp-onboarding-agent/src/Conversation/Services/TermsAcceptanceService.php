<?php

declare(strict_types=1);

namespace NxTutors\WhatsAppOnboarding\Conversation\Services;

use NxTutors\WhatsAppOnboarding\Profile\Models\OnboardingConversation;
use NxTutors\WhatsAppOnboarding\Profile\Models\OnboardingTermsAcceptance;

final class TermsAcceptanceService
{
    public function termsUrlForRole(string $role): string
    {
        $key = $role === 'tutor' ? 'tutor_url' : 'student_url';

        return (string) config("whatsapp_onboarding.terms.{$key}");
    }

    public function privacyUrlForRole(string $role): string
    {
        $key = $role === 'tutor' ? 'tutor_privacy_url' : 'student_privacy_url';

        return (string) config("whatsapp_onboarding.terms.{$key}");
    }

    /** @param array<string, mixed> $metadata */
    public function accept(OnboardingConversation $conversation, string $messageId, array $metadata = []): void
    {
        $role = (string) $conversation->role;
        $safeMetadata = $this->safeMetadata($metadata);
        $conversation->forceFill([
            'terms_url' => $this->termsUrlForRole($role),
            'terms_version' => (string) config('whatsapp_onboarding.terms.version', 'current'),
            'terms_role' => $role,
            'terms_accepted_message_id' => $messageId,
            'terms_metadata' => $safeMetadata,
            'terms_accepted_at' => now(),
        ])->save();

        OnboardingTermsAcceptance::query()->create([
            'onboarding_conversation_id' => $conversation->id,
            'role' => $role,
            'terms_url' => $this->termsUrlForRole($role),
            'terms_version' => (string) config('whatsapp_onboarding.terms.version', 'current'),
            'accepted_at' => now(),
            'acceptance_message_id' => $messageId,
            'acceptance_text_hash' => $this->hash((string) ($metadata['acceptance_text'] ?? '')),
            'user_phone_hash' => $this->hash((string) $conversation->wa_phone),
            'ip_hash' => isset($metadata['request']['ip']) ? $this->hash((string) $metadata['request']['ip']) : null,
            'user_agent_hash' => isset($metadata['request']['user_agent']) ? $this->hash((string) $metadata['request']['user_agent']) : null,
            'metadata' => [
                'event_id' => $metadata['event_id'] ?? null,
                'webhook_timestamp' => $metadata['webhook_timestamp'] ?? null,
            ],
        ]);
    }

    private function hash(string $value): string
    {
        return hash_hmac('sha256', $value, (string) config('app.key', 'local-testing-key'));
    }

    /** @param array<string, mixed> $metadata */
    private function safeMetadata(array $metadata): array
    {
        return [
            'event_id' => $metadata['event_id'] ?? null,
            'webhook_timestamp' => $metadata['webhook_timestamp'] ?? null,
            'ip_hash' => isset($metadata['request']['ip']) ? $this->hash((string) $metadata['request']['ip']) : null,
            'user_agent_hash' => isset($metadata['request']['user_agent']) ? $this->hash((string) $metadata['request']['user_agent']) : null,
        ];
    }
}
