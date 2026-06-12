<?php

declare(strict_types=1);

namespace NxTutors\WhatsAppOnboarding\Tutor\Validators;

use NxTutors\WhatsAppOnboarding\Common\Validators\CommonFieldValidator;
use NxTutors\WhatsAppOnboarding\Common\Validators\ValidationResult;

final readonly class TutorFieldValidator
{
    public function __construct(private CommonFieldValidator $common)
    {
    }

    public function validate(string $field, string $value): ValidationResult
    {
        return match ($field) {
            'education', 'degree' => mb_strlen(trim($value)) >= 2 && mb_strlen(trim($value)) <= 255 ? ValidationResult::ok() : ValidationResult::fail([__('nx-whatsapp-onboarding::errors.invalid_qualification')]),
            'other_education' => ValidationResult::ok(),
            'experience' => preg_match('/^[0-9]{1,2}([.][0-9])?(\s*(years?|yrs?))?$/i', trim($value)) === 1
                ? ValidationResult::ok()
                : ValidationResult::fail([__('nx-whatsapp-onboarding::errors.invalid_experience')]),
            'document_type' => in_array(mb_strtolower(trim($value)), ['aadhaar', 'pan', 'passport', 'driving license', 'voter id', 'other'], true)
                ? ValidationResult::ok()
                : ValidationResult::fail([__('nx-whatsapp-onboarding::errors.invalid_document_type')]),
            'document_number' => preg_match('/^[A-Za-z0-9 -]{4,255}$/', trim($value)) === 1
                ? ValidationResult::ok()
                : ValidationResult::fail([__('nx-whatsapp-onboarding::errors.invalid_document_number')]),
            'front_image', 'back_image' => trim($value) !== '' ? ValidationResult::ok() : ValidationResult::fail([__('nx-whatsapp-onboarding::errors.missing_upload')]),
            'profile', 'profile_desc', 'pro_desc' => mb_strlen(trim($value)) >= 2 && mb_strlen(trim($value)) <= 1000 ? ValidationResult::ok() : ValidationResult::fail([__('nx-whatsapp-onboarding::errors.invalid_profile_text')]),
            default => $this->common->validate($field, $value),
        };
    }
}
