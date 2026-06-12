<?php

declare(strict_types=1);

namespace NxTutors\WhatsAppOnboarding\Student\DTO;

final readonly class StudentDraft
{
    public function __construct(
        public string $userId,
        public string $name,
        public string $email,
        public string $phone,
        public ?string $dob,
        public ?string $gender,
        public ?string $classType,
        public string $forClass,
        public ?string $budget,
        public ?string $address,
        public ?string $city,
        public ?string $district,
        public ?string $state,
        public ?string $pincode,
        public ?string $profileDesc,
    ) {
    }
}
