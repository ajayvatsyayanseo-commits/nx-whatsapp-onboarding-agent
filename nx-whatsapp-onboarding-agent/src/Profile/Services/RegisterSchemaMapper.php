<?php

declare(strict_types=1);

namespace NxTutors\WhatsAppOnboarding\Profile\Services;

use NxTutors\WhatsAppOnboarding\Student\DTO\StudentDraft;
use NxTutors\WhatsAppOnboarding\Tutor\DTO\TutorDraft;

final class RegisterSchemaMapper
{
    public function studentToRegisterAttributes(StudentDraft $draft, string $hashedPassword): array
    {
        return $this->filter([
            'user_id' => $draft->userId,
            'name' => $draft->name,
            'email' => $draft->email,
            'password' => $hashedPassword,
            'c_password' => null,
            'user_type' => 'student',
            'phone' => $draft->phone,
            'dob' => $draft->dob,
            'gender' => $draft->gender,
            'date' => now(),
            'address' => $draft->address,
            'city' => $draft->city,
            'district' => $draft->district,
            'state' => $draft->state,
            'pincode' => $draft->pincode,
            'otp_status' => 'verified',
            'status' => (string) config('whatsapp_onboarding.profile.student_status', 'active'),
            'join_as' => 'student',
            'class_type' => $draft->classType,
            'for_class' => $draft->forClass,
            'budget' => $draft->budget,
            'profile' => $draft->profileDesc !== null ? 'Student need' : null,
            'profile_desc' => $draft->profileDesc ?? $this->studentNeedSummary($draft),
            'pro_desc' => $draft->profileDesc ?? $this->studentNeedSummary($draft),
        ]);
    }

    public function tutorToRegisterAttributes(TutorDraft $draft, string $hashedPassword): array
    {
        $hasDocuments = $draft->frontImage !== null || $draft->backImage !== null || $draft->degreeCertificate !== null;
        $defaultStatus = $hasDocuments && (bool) config('whatsapp_onboarding.profile.tutor_documents_require_review', true)
            ? 'pending_review'
            : (string) config('whatsapp_onboarding.profile.tutor_status', 'pending_review');

        return $this->filter([
            'user_id' => $draft->userId,
            'name' => $draft->name,
            'email' => $draft->email,
            'password' => $hashedPassword,
            'c_password' => null,
            'user_type' => 'tutor',
            'phone' => $draft->phone,
            'dob' => $draft->dob,
            'gender' => $draft->gender,
            'date' => now(),
            'address' => $draft->address,
            'city' => $draft->city,
            'district' => $draft->district,
            'state' => $draft->state,
            'pincode' => $draft->pincode,
            'otp_status' => 'verified',
            'status' => $defaultStatus,
            'join_as' => 'tutor',
            'class_type' => $draft->classType,
            'for_class' => $draft->forClass,
            'frount_image' => $draft->frontImage,
            'back_image' => $draft->backImage,
            'degree' => $draft->degreeCertificate,
            'experience' => $draft->experience,
            'education' => $draft->education,
            'budget' => $draft->budgetOrFee,
            'other_education' => $draft->otherEducation,
            'document_type' => $draft->documentType,
            'document_number' => $draft->documentNumber,
            'profile' => $draft->profile,
            'profile_desc' => $draft->profileDesc,
            'pro_desc' => $draft->proDesc,
        ]);
    }

    /** @param array<string, mixed> $attributes */
    private function filter(array $attributes): array
    {
        return array_filter($attributes, static fn (mixed $value, string $key): bool => $key === 'c_password' || $value !== null, ARRAY_FILTER_USE_BOTH);
    }

    private function studentNeedSummary(StudentDraft $draft): string
    {
        return trim("Needs tutoring for {$draft->forClass}" . ($draft->classType ? " via {$draft->classType}" : ''));
    }
}
