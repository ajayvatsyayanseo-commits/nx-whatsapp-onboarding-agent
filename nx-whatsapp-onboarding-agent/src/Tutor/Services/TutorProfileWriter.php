<?php

declare(strict_types=1);

namespace NxTutors\WhatsAppOnboarding\Tutor\Services;

use NxTutors\WhatsAppOnboarding\Profile\Models\OnboardingConversation;
use NxTutors\WhatsAppOnboarding\Profile\Models\Register;
use NxTutors\WhatsAppOnboarding\Profile\DTO\ProfileWriteResult;
use NxTutors\WhatsAppOnboarding\Profile\Repositories\RegisterRepository;
use NxTutors\WhatsAppOnboarding\Profile\Services\LoginCredentialService;
use NxTutors\WhatsAppOnboarding\Profile\Services\ProfileMetadataService;
use NxTutors\WhatsAppOnboarding\Profile\Services\RegisterSchemaMapper;

final readonly class TutorProfileWriter
{
    public function __construct(
        private TutorProfileAssembler $assembler,
        private LoginCredentialService $credentials,
        private RegisterSchemaMapper $mapper,
        private RegisterRepository $repository,
        private ProfileMetadataService $metadata,
    ) {
    }

    public function write(OnboardingConversation $conversation): ProfileWriteResult
    {
        $credential = $this->credentials->generateTemporaryPassword();
        $draft = $this->assembler->assemble($conversation);
        $conversation->forceFill(['context' => array_merge($conversation->context ?? [], [
            'user_id' => $draft->userId,
        ])])->save();

        $attributes = $this->mapper->tutorToRegisterAttributes($draft, $credential['password_hash']);
        $dryRun = ! (bool) config('whatsapp_onboarding.profile.create_real_profile', true);
        $register = $dryRun ? $this->dryRunRegister($attributes) : $this->repository->createProfile($attributes);
        $this->metadata->record($conversation, (string) $register->user_id, $dryRun, ['created_via' => 'whatsapp_tutor', 'status' => $register->status]);
        $this->metadata->purgeSensitiveDraft($conversation);

        return new ProfileWriteResult($register, $credential['temporary_password']);
    }

    /** @param array<string, mixed> $attributes */
    private function dryRunRegister(array $attributes): Register
    {
        $register = new Register();
        $register->forceFill($attributes);

        return $register;
    }
}
