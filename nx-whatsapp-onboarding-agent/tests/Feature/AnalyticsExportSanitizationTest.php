<?php

declare(strict_types=1);

namespace NxTutors\WhatsAppOnboarding\Tests\Feature;

use NxTutors\WhatsAppOnboarding\Tests\TestCase;

final class AnalyticsExportSanitizationTest extends TestCase
{
    public function testAthenaSchemaDoesNotContainRawPiiColumns(): void
    {
        $schema = (string) file_get_contents(__DIR__ . '/../../infra/glue/onboarding_events_athena.sql');

        self::assertStringNotContainsString('phone', mb_strtolower($schema));
        self::assertStringNotContainsString('email', mb_strtolower($schema));
        self::assertStringNotContainsString('document_number', mb_strtolower($schema));
    }
}
