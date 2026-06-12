<?php

declare(strict_types=1);

namespace NxTutors\WhatsAppOnboarding\Tests\Feature;

use NxTutors\WhatsAppOnboarding\Profile\Services\RegisterSchemaMapper;
use NxTutors\WhatsAppOnboarding\Student\DTO\StudentDraft;
use NxTutors\WhatsAppOnboarding\Tests\TestCase;

final class StudentProfileCreationTest extends TestCase
{
    public function testStudentDraftMapsToLegacyRegisterColumns(): void
    {
        $attributes = (new RegisterSchemaMapper())->studentToRegisterAttributes(new StudentDraft(
            userId: 'NXS-2026-ABC123',
            name: 'Asha Student',
            email: 'asha@example.test',
            phone: '+919999999999',
            dob: null,
            gender: null,
            classType: 'online',
            forClass: 'Class 10 Maths',
            budget: '5000 monthly',
            address: null,
            city: 'Bengaluru',
            district: null,
            state: 'Karnataka',
            pincode: '560001',
            profileDesc: 'Needs maths support',
        ), 'hashed-password');

        self::assertSame('student', $attributes['user_type']);
        self::assertSame('student', $attributes['join_as']);
        self::assertSame('verified', $attributes['otp_status']);
        self::assertSame('hashed-password', $attributes['password']);
        self::assertNull($attributes['c_password']);
    }
}
