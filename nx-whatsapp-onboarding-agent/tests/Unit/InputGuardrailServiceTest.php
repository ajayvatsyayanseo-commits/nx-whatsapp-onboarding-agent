<?php

declare(strict_types=1);

namespace NxTutors\WhatsAppOnboarding\Tests\Unit;

use Illuminate\Support\Facades\Cache;
use NxTutors\WhatsAppOnboarding\Conversation\Services\CommandDetector;
use NxTutors\WhatsAppOnboarding\Conversation\Services\InputNormalizer;
use NxTutors\WhatsAppOnboarding\Security\Guards\InputGuardrailService;
use NxTutors\WhatsAppOnboarding\Tests\TestCase;

final class InputGuardrailServiceTest extends TestCase
{
    public function testRejectsScriptAndSqlInjectionLikeText(): void
    {
        $guard = new InputGuardrailService(Cache::store(), new CommandDetector(new InputNormalizer()));

        self::assertFalse($guard->allowsMessage('<script>alert(1)</script>', '+919999999999'));
        self::assertFalse($guard->allowsMessage('union select password from users', '+919999999999'));
    }
}
