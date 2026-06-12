<?php

declare(strict_types=1);

namespace NxTutors\WhatsAppOnboarding\Tests\Unit;

use NxTutors\WhatsAppOnboarding\Conversation\Services\CommandDetector;
use NxTutors\WhatsAppOnboarding\Conversation\Services\ConversationCommand;
use NxTutors\WhatsAppOnboarding\Conversation\Services\InputNormalizer;
use PHPUnit\Framework\TestCase;

final class CommandDetectorTest extends TestCase
{
    public function testDetectsRoleAndEditCommands(): void
    {
        $detector = new CommandDetector(new InputNormalizer());

        self::assertSame(ConversationCommand::Student, $detector->detect('1')['command']);
        self::assertSame(ConversationCommand::Tutor, $detector->detect('tutor')['command']);
        self::assertSame('document_number', $detector->detect('edit document number')['argument']);
    }

    public function testTermsAgreementRequiresExplicitAgreementVariants(): void
    {
        $detector = new CommandDetector(new InputNormalizer());

        self::assertSame(ConversationCommand::Agree, $detector->detect('I AGREE')['command']);
        self::assertSame(ConversationCommand::Agree, $detector->detect('yes i agree')['command']);
        self::assertNotSame(ConversationCommand::Agree, $detector->detect('ok')['command']);
        self::assertNotSame(ConversationCommand::Agree, $detector->detect('maybe')['command']);
    }
}
