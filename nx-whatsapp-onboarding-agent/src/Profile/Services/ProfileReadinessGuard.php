<?php

declare(strict_types=1);

namespace NxTutors\WhatsAppOnboarding\Profile\Services;

use NxTutors\WhatsAppOnboarding\Student\Flow\StudentFlowDefinition;
use NxTutors\WhatsAppOnboarding\Tutor\Flow\TutorFlowDefinition;
use NxTutors\WhatsAppOnboarding\Security\Encryption\SensitiveDraftCrypt;

final readonly class ProfileReadinessGuard
{
    public function __construct(
        private StudentFlowDefinition $studentFlow,
        private TutorFlowDefinition $tutorFlow,
        private DuplicateProfileGuard $duplicates,
        private SensitiveDraftCrypt $draftCrypt,
    ) {
    }

    /** @param array<string, mixed> $context */
    public function check(string $role, array $context): ProfileReadinessResult
    {
        $context = $this->draftCrypt->decryptContext($context);
        $required = $this->requiredFieldsForWebsite($role);
        $missing = [];

        foreach ($required as $field) {
            if (! array_key_exists($field, $context) || trim((string) $context[$field]) === '') {
                $missing[] = $field;
            }
        }

        if ($missing !== []) {
            return ProfileReadinessResult::missing($missing);
        }

        $duplicate = $this->duplicates->check($context);
        if (! $duplicate->valid) {
            return ProfileReadinessResult::duplicate((string) $duplicate->field);
        }

        return ProfileReadinessResult::ok();
    }

    /** @return list<string> */
    private function requiredFieldsForWebsite(string $role): array
    {
        if (! (bool) config('whatsapp_onboarding.nxtutors_legacy.enabled', true)) {
            return $role === 'tutor' ? $this->tutorFlow->requiredFields() : $this->studentFlow->requiredFields();
        }

        $required = ['name', 'email', 'phone', 'terms_accepted_at', 'otp_verified_at'];
        if ($role === 'tutor' && (bool) config('whatsapp_onboarding.nxtutors_legacy.tutor_require_documents_before_create', false)) {
            return array_merge($required, ['document_type', 'document_number', 'front_image']);
        }

        return $required;
    }
}
