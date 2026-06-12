<?php

declare(strict_types=1);

namespace NxTutors\WhatsAppOnboarding\Tests\Unit;

use NxTutors\WhatsAppOnboarding\Common\Validators\CommonFieldValidator;
use NxTutors\WhatsAppOnboarding\Student\Validators\StudentFieldValidator;
use NxTutors\WhatsAppOnboarding\Tests\TestCase;
use NxTutors\WhatsAppOnboarding\Tutor\Validators\TutorFieldValidator;

final class FieldValidatorTest extends TestCase
{
    public function testStudentValidatorAcceptsValidCoreFields(): void
    {
        $validator = new StudentFieldValidator(new CommonFieldValidator());

        self::assertTrue($validator->validate('email', 'student@example.test')->valid);
        self::assertTrue($validator->validate('pincode', '560001')->valid);
        self::assertTrue($validator->validate('budget', '5000 monthly')->valid);
    }

    public function testTutorValidatorRejectsInvalidDocumentType(): void
    {
        $validator = new TutorFieldValidator(new CommonFieldValidator());

        self::assertFalse($validator->validate('document_type', 'library card')->valid);
    }
}
