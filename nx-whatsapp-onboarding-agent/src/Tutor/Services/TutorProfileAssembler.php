<?php

declare(strict_types=1);

namespace NxTutors\WhatsAppOnboarding\Tutor\Services;

use NxTutors\WhatsAppOnboarding\Profile\Models\OnboardingConversation;
use NxTutors\WhatsAppOnboarding\Profile\Services\UserIdGenerator;
use NxTutors\WhatsAppOnboarding\Common\Support\PhoneNormalizer;
use NxTutors\WhatsAppOnboarding\Security\Encryption\SensitiveDraftCrypt;
use NxTutors\WhatsAppOnboarding\Tutor\DTO\TutorDraft;

final readonly class TutorProfileAssembler
{
    public function __construct(
        private UserIdGenerator $userIdGenerator,
        private SensitiveDraftCrypt $draftCrypt,
        private PhoneNormalizer $phones,
    ) {
    }

    public function assemble(OnboardingConversation $conversation): TutorDraft
    {
        $context = $this->draftCrypt->decryptContext($conversation->context ?? []);

        return new TutorDraft(
            userId: (string) ($context['user_id'] ?? $this->userIdGenerator->generate('tutor')),
            name: (string) $context['name'],
            email: (string) $context['email'],
            phone: $this->phones->forStorage((string) ($context['phone'] ?? $conversation->wa_phone)),
            dob: $this->nullable($context['dob'] ?? null),
            gender: $this->nullable($context['gender'] ?? null),
            education: (string) ($context['education'] ?? ''),
            otherEducation: $this->nullable($context['other_education'] ?? null),
            experience: (string) ($context['experience'] ?? ''),
            degreeCertificate: $this->nullable($context['degree_certificate'] ?? $context['degree'] ?? null),
            classType: $this->nullable($context['class_type'] ?? null),
            forClass: (string) ($context['for_class'] ?? ''),
            budgetOrFee: $this->nullable($context['budget'] ?? null),
            address: $this->nullable($context['address'] ?? null),
            city: $this->nullable($context['city'] ?? null),
            district: $this->nullable($context['district'] ?? null),
            state: $this->nullable($context['state'] ?? null),
            pincode: $this->nullable($context['pincode'] ?? null),
            documentType: (string) ($context['document_type'] ?? ''),
            documentNumber: (string) ($context['document_number'] ?? ''),
            frontImage: $this->nullable($context['front_image'] ?? null),
            backImage: $this->nullable($context['back_image'] ?? null),
            profile: (string) ($context['profile'] ?? 'Tutor Partner'),
            profileDesc: (string) ($context['profile_desc'] ?? $context['for_class'] ?? ''),
            proDesc: (string) ($context['pro_desc'] ?? $context['experience'] ?? ''),
        );
    }

    private function nullable(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
