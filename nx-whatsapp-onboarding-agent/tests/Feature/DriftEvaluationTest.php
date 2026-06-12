<?php

declare(strict_types=1);

namespace NxTutors\WhatsAppOnboarding\Tests\Feature;

use NxTutors\WhatsAppOnboarding\Observability\DriftEvaluation\DriftEvaluationService;
use NxTutors\WhatsAppOnboarding\Tests\TestCase;

final class DriftEvaluationTest extends TestCase
{
    public function testDriftEvaluationReturnsReportShape(): void
    {
        $report = (new DriftEvaluationService())->evaluate();

        self::assertArrayHasKey('metrics', $report);
        self::assertArrayHasKey('alerts', $report);
    }
}
