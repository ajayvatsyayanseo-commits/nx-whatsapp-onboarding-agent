<?php

declare(strict_types=1);

namespace NxTutors\WhatsAppOnboarding\Tests\Unit;

use NxTutors\WhatsAppOnboarding\Profile\Services\RegisterSchemaMapper;
use NxTutors\WhatsAppOnboarding\Tutor\DTO\TutorDraft;
use NxTutors\WhatsAppOnboarding\Tests\TestCase;

final class RegisterSchemaMapperTest extends TestCase
{
    public function testMapsCleanFrontImageToLegacyTypoColumn(): void
    {
        $attributes = (new RegisterSchemaMapper())->tutorToRegisterAttributes(new TutorDraft(
            userId: 'NXT-2026-ABC123',
            name: 'Asha Tutor',
            email: 'asha@example.test',
            phone: '+919999999999',
            dob: null,
            gender: null,
            education: 'MSc',
            otherEducation: null,
            experience: '3 years',
            degreeCertificate: 's3://bucket/degree.pdf',
            classType: 'online',
            forClass: 'Maths',
            budgetOrFee: null,
            address: null,
            city: null,
            district: null,
            state: null,
            pincode: null,
            documentType: 'PAN',
            documentNumber: 'ABCDE1234F',
            frontImage: 's3://bucket/front.jpg',
            backImage: 's3://bucket/back.jpg',
            profile: 'Math tutor',
            profileDesc: 'Short profile',
            proDesc: 'Professional profile',
        ), 'hashed-password');

        self::assertSame('s3://bucket/front.jpg', $attributes['frount_image']);
        self::assertArrayNotHasKey('front_image', $attributes);
        self::assertNull($attributes['c_password']);
        self::assertSame('s3://bucket/degree.pdf', $attributes['degree']);
    }
}
