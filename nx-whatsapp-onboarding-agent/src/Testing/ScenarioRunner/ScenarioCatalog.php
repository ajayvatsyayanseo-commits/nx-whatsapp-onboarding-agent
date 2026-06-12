<?php

declare(strict_types=1);

namespace NxTutors\WhatsAppOnboarding\Testing\ScenarioRunner;

final class ScenarioCatalog
{
    /** @return array<string, array<string, mixed>> */
    public function all(): array
    {
        $path = __DIR__ . '/../Fixtures/conversation_scenarios.json';

        return json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
    }
}
