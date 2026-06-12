<?php

declare(strict_types=1);

namespace NxTutors\WhatsAppOnboarding\Student\Validators;

use NxTutors\WhatsAppOnboarding\Common\Validators\CommonFieldValidator;
use NxTutors\WhatsAppOnboarding\Common\Validators\ValidationResult;

final readonly class StudentFieldValidator
{
    public function __construct(private CommonFieldValidator $common)
    {
    }

    public function validate(string $field, string $value): ValidationResult
    {
        return match ($field) {
            default => $this->common->validate($field, $value),
        };
    }
}
