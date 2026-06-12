<?php

declare(strict_types=1);

namespace NxTutors\WhatsAppOnboarding\Tests\Unit;

use NxTutors\WhatsAppOnboarding\Conversation\StateMachine\ConversationState;
use NxTutors\WhatsAppOnboarding\Conversation\StateMachine\StateMachineEngine;
use PHPUnit\Framework\TestCase;

final class ConversationStateTest extends TestCase
{
    public function testEnumContainsTerminalStates(): void
    {
        self::assertSame('COMPLETED', ConversationState::Completed->value);
        self::assertSame('HUMAN_HANDOFF', ConversationState::HumanHandoff->value);
        self::assertSame('EXPIRED', ConversationState::Expired->value);
    }

    public function testEngineAllowsExplicitSignupTransition(): void
    {
        $transition = (new StateMachineEngine())->transition(
            ConversationState::New,
            ConversationState::WaitingRoleSelection,
            'test',
        );

        self::assertSame(ConversationState::WaitingRoleSelection, $transition->to);
    }
}
