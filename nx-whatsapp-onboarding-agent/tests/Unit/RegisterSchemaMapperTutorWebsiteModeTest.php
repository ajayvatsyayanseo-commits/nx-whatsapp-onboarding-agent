<?php

declare(strict_types=1);

namespace NxTutors\WhatsAppOnboarding\Tests\Unit;

use NxTutors\WhatsAppOnboarding\Profile\Services\RegisterSchemaMapper;
use NxTutors\WhatsAppOnboarding\Tests\TestCase;
use NxTutors\WhatsAppOnboarding\Tutor\DTO\TutorDraft;

final class RegisterSchemaMapperTutorWebsiteModeTest extends TestCase
{
    public function testMapsTutorToCurrentWebsiteValues(): void
    {
        $attributes = (new RegisterSchemaMapper())->tutorToRegisterAttributes(new TutorDraft(
            userId: '102',
            name: 'Ajay Tutor',
            email: 'ajay@example.test',
            phone: '9999999999',
            dob: null,
            gender: null,
            education: '',
            otherEducation: null,
            experience: '3 years',
            degreeCertificate: 'degree.pdf',
            classType: 'Online',
            forClass: 'Maths; Class 8 to 10',
            budgetOrFee: '500',
            address: null,
            city: 'Delhi',
            district: null,
            state: null,
            pincode: null,
            documentType: '',
            documentNumber: '',
            frontImage: 'front.jpg',
            backImage: 'back.jpg',
            profile: 'Tutor Partner',
            profileDesc: 'Maths',
            proDesc: '3 years',
        ), 'hashed');

        self::assertSame('t', $attributes['status']);
        self::assertSame('t', $attributes['otp_status']);
        self::assertSame('teacher', $attributes['join_as']);
        self::assertSame('Individual', $attributes['user_type']);
        self::assertSame('front.jpg', $attributes['frount_image']);
        self::assertArrayNotHasKey('front_image', $attributes);
        self::assertNull($attributes['c_password']);
    }
}
