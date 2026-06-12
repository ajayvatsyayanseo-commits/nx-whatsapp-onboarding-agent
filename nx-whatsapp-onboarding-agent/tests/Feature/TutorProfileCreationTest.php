<?php

declare(strict_types=1);

namespace NxTutors\WhatsAppOnboarding\Tests\Feature;

use NxTutors\WhatsAppOnboarding\Profile\Services\RegisterSchemaMapper;
use NxTutors\WhatsAppOnboarding\Tests\TestCase;
use NxTutors\WhatsAppOnboarding\Tutor\DTO\TutorDraft;

final class TutorProfileCreationTest extends TestCase
{
    public function testTutorDraftMapsCleanNamesToLegacyColumns(): void
    {
        $attributes = (new RegisterSchemaMapper())->tutorToRegisterAttributes(new TutorDraft(
            userId: 'NXT-2026-ABC123',
            name: 'Ravi Tutor',
            email: 'ravi@example.test',
            phone: '+918888888888',
            dob: null,
            gender: null,
            education: 'MSc',
            otherEducation: null,
            experience: '5 years',
            degreeCertificate: 'nxtutors/onboarding/degree.pdf',
            classType: 'online',
            forClass: 'Maths',
            budgetOrFee: '800 hourly',
            address: null,
            city: 'Mumbai',
            district: null,
            state: 'Maharashtra',
            pincode: '400001',
            documentType: 'PAN',
            documentNumber: 'ABCDE1234F',
            frontImage: 'nxtutors/onboarding/front.jpg',
            backImage: 'nxtutors/onboarding/back.jpg',
            profile: 'Maths Tutor',
            profileDesc: 'Short profile',
            proDesc: 'Professional profile',
        ), 'hashed-password');

        self::assertSame('tutor', $attributes['user_type']);
        self::assertSame('nxtutors/onboarding/front.jpg', $attributes['frount_image']);
        self::assertSame('nxtutors/onboarding/back.jpg', $attributes['back_image']);
        self::assertSame('nxtutors/onboarding/degree.pdf', $attributes['degree']);
        self::assertSame('pending_review', $attributes['status']);
    }
}
