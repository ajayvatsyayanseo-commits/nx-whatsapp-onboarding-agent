<?php

declare(strict_types=1);

namespace NxTutors\WhatsAppOnboarding\Tests\Unit;

use NxTutors\WhatsAppOnboarding\Profile\Services\RegisterSchemaMapper;
use NxTutors\WhatsAppOnboarding\Student\DTO\StudentDraft;
use NxTutors\WhatsAppOnboarding\Tests\TestCase;

final class RegisterSchemaMapperStudentWebsiteModeTest extends TestCase
{
    public function testMapsStudentToCurrentWebsiteValues(): void
    {
        $attributes = (new RegisterSchemaMapper())->studentToRegisterAttributes(new StudentDraft(
            userId: '101',
            name: 'Asha Student',
            email: 'asha@example.test',
            phone: '9999999999',
            dob: null,
            gender: null,
            classType: 'online',
            forClass: 'Class 10 Maths',
            budget: null,
            address: null,
            city: null,
            district: null,
            state: null,
            pincode: null,
            profileDesc: null,
        ), 'hashed');

        self::assertSame('t', $attributes['status']);
        self::assertSame('t', $attributes['otp_status']);
        self::assertSame('student', $attributes['join_as']);
        self::assertSame('student', $attributes['user_type']);
        self::assertNull($attributes['c_password']);
    }
}
