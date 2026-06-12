<?php

declare(strict_types=1);

namespace NxTutors\WhatsAppOnboarding\Tutor\DTO;

final readonly class TutorDraft
{
    public function __construct(
        public string $userId,
        public string $name,
        public string $email,
        public string $phone,
        public ?string $dob,
        public ?string $gender,
        public string $education,
        public ?string $otherEducation,
        public string $experience,
        public ?string $degreeCertificate,
        public ?string $classType,
        public string $forClass,
        public ?string $budgetOrFee,
        public ?string $address,
        public ?string $city,
        public ?string $district,
        public ?string $state,
        public ?string $pincode,
        public string $documentType,
        public string $documentNumber,
        public ?string $frontImage,
        public ?string $backImage,
        public string $profile,
        public string $profileDesc,
        public string $proDesc,
    ) {
    }
}
