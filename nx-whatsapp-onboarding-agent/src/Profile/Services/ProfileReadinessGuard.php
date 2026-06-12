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
        $required = $role === 'tutor' ? $this->tutorFlow->requiredFields() : $this->studentFlow->requiredFields();
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
}
