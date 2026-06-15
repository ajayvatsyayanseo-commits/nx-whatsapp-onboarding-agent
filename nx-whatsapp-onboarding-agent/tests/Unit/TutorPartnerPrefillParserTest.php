<?php

declare(strict_types=1);

namespace NxTutors\WhatsAppOnboarding\Tests\Unit;

use NxTutors\WhatsAppOnboarding\Tutor\Services\TutorPartnerPrefillParser;
use PHPUnit\Framework\TestCase;

final class TutorPartnerPrefillParserTest extends TestCase
{
    public function testParsesTutorPartnerMessage(): void
    {
        $fields = (new TutorPartnerPrefillParser())->parse("Hey NXtutors, I want to signup as a Tutor Partner.\nName: Ajay\nSubjects: Maths\nClasses: Class 8 to 10\nExperience: 3 years\nLocation: Delhi\nPreferred mode: Online\nHourly rate: 500\nAvailability: Monday\nWhatsApp number: 9876543210");

        self::assertSame('tutor', $fields['role']);
        self::assertSame('Ajay', $fields['name']);
        self::assertSame('Maths; Class 8 to 10', $fields['for_class']);
        self::assertSame('3 years', $fields['experience']);
        self::assertSame('Online', $fields['class_type']);
        self::assertSame('500', $fields['budget']);
    }
}
