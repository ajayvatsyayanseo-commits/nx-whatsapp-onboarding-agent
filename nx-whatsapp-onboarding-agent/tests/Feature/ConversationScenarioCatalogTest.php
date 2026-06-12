<?php

declare(strict_types=1);

namespace NxTutors\WhatsAppOnboarding\Tests\Feature;

use NxTutors\WhatsAppOnboarding\Testing\ScenarioRunner\ScenarioCatalog;
use PHPUnit\Framework\TestCase;

final class ConversationScenarioCatalogTest extends TestCase
{
    public function testScenarioCatalogContainsRequestedCases(): void
    {
        $scenarios = (new ScenarioCatalog())->all();

        self::assertCount(12, $scenarios);
        self::assertArrayHasKey('student_complete', $scenarios);
        self::assertArrayHasKey('tutor_complete', $scenarios);
        self::assertArrayHasKey('invalid_email_twice', $scenarios);
        self::assertArrayHasKey('duplicate_phone', $scenarios);
        self::assertArrayHasKey('edit_during_review', $scenarios);
        self::assertArrayHasKey('cancel', $scenarios);
        self::assertArrayHasKey('restart', $scenarios);
        self::assertArrayHasKey('rapid_messages', $scenarios);
        self::assertArrayHasKey('otp_expired', $scenarios);
        self::assertArrayHasKey('tutor_uploads_documents', $scenarios);
        self::assertArrayHasKey('terms_reject_maybe_ok', $scenarios);
        self::assertArrayHasKey('hitl_after_repeated_invalid', $scenarios);
    }
}
