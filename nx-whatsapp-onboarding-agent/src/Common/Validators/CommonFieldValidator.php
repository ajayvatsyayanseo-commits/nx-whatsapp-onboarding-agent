<?php

declare(strict_types=1);

namespace NxTutors\WhatsAppOnboarding\Common\Validators;

final class CommonFieldValidator
{
    public function validate(string $field, string $value): ValidationResult
    {
        $value = trim($value);

        return match ($field) {
            'name' => mb_strlen($value) >= 2 && mb_strlen($value) <= 255 ? ValidationResult::ok() : ValidationResult::fail([__('nx-whatsapp-onboarding::errors.invalid_name')]),
            'email' => filter_var($value, FILTER_VALIDATE_EMAIL) ? ValidationResult::ok() : ValidationResult::fail([__('nx-whatsapp-onboarding::errors.invalid_email')]),
            'dob' => $this->isDate($value) ? ValidationResult::ok() : ValidationResult::fail([__('nx-whatsapp-onboarding::errors.invalid_dob')]),
            'gender' => in_array(mb_strtolower($value), ['male', 'female', 'other'], true) ? ValidationResult::ok() : ValidationResult::fail([__('nx-whatsapp-onboarding::errors.invalid_gender')]),
            'address' => mb_strlen($value) >= 5 && mb_strlen($value) <= 500 ? ValidationResult::ok() : ValidationResult::fail([__('nx-whatsapp-onboarding::errors.invalid_address')]),
            'city', 'district', 'state' => mb_strlen($value) >= 2 && mb_strlen($value) <= 120 ? ValidationResult::ok() : ValidationResult::fail([__('nx-whatsapp-onboarding::errors.invalid_location')]),
            'pincode' => $this->isValidPincode($value) ? ValidationResult::ok() : ValidationResult::fail([__('nx-whatsapp-onboarding::errors.invalid_pincode')]),
            'class_type', 'for_class' => mb_strlen($value) >= 2 && mb_strlen($value) <= 255 ? ValidationResult::ok() : ValidationResult::fail([__('nx-whatsapp-onboarding::errors.invalid_class')]),
            'budget' => $this->isBudget($value) ? ValidationResult::ok() : ValidationResult::fail([__('nx-whatsapp-onboarding::errors.invalid_budget')]),
            'profile', 'profile_desc', 'pro_desc' => mb_strlen($value) >= 2 && mb_strlen($value) <= 1000 ? ValidationResult::ok() : ValidationResult::fail([__('nx-whatsapp-onboarding::errors.invalid_profile_text')]),
            default => ValidationResult::ok(),
        };
    }

    private function isDate(string $value): bool
    {
        $date = \DateTimeImmutable::createFromFormat('Y-m-d', trim($value));

        if ($date === false || $date->format('Y-m-d') !== trim($value)) {
            return false;
        }

        if ($date > new \DateTimeImmutable('today')) {
            return false;
        }

        $minAge = config('whatsapp_onboarding_state_machine.min_age_years');
        if ($minAge !== null) {
            return $date <= (new \DateTimeImmutable('today'))->modify('-' . (int) $minAge . ' years');
        }

        return true;
    }

    private function isValidPincode(string $value): bool
    {
        if ((bool) config('whatsapp_onboarding_security.validation.india_pincode', true)) {
            return preg_match('/^[1-9][0-9]{5}$/', $value) === 1;
        }

        return preg_match('/^[0-9A-Za-z -]{3,12}$/', $value) === 1;
    }

    private function isBudget(string $value): bool
    {
        return mb_strlen($value) <= 80
            && preg_match('/[0-9]{2,8}/', $value) === 1
            && preg_match('/^[0-9A-Za-z ,.+\-\/]+$/', $value) === 1;
    }
}
