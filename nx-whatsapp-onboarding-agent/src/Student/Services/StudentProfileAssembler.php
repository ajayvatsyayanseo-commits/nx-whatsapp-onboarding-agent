<?php

declare(strict_types=1);

namespace NxTutors\WhatsAppOnboarding\Student\Services;

use NxTutors\WhatsAppOnboarding\Profile\Models\OnboardingConversation;
use NxTutors\WhatsAppOnboarding\Profile\Services\UserIdGenerator;
use NxTutors\WhatsAppOnboarding\Student\DTO\StudentDraft;

final readonly class StudentProfileAssembler
{
    public function __construct(private UserIdGenerator $userIdGenerator)
    {
    }

    public function assemble(OnboardingConversation $conversation): StudentDraft
    {
        $context = $conversation->context ?? [];

        return new StudentDraft(
            userId: (string) ($context['user_id'] ?? $this->userIdGenerator->generate('student')),
            name: (string) $context['name'],
            email: (string) $context['email'],
            phone: (string) ($context['phone'] ?? $conversation->wa_phone),
            dob: $this->nullable($context['dob'] ?? null),
            gender: $this->nullable($context['gender'] ?? null),
            classType: $this->nullable($context['class_type'] ?? null),
            forClass: (string) $context['for_class'],
            budget: $this->nullable($context['budget'] ?? null),
            address: $this->nullable($context['address'] ?? null),
            city: $this->nullable($context['city'] ?? null),
            district: $this->nullable($context['district'] ?? null),
            state: $this->nullable($context['state'] ?? null),
            pincode: $this->nullable($context['pincode'] ?? null),
            profileDesc: $this->nullable($context['profile_desc'] ?? null),
        );
    }

    private function nullable(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
