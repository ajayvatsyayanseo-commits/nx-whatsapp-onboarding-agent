<?php

declare(strict_types=1);

namespace NxTutors\WhatsAppOnboarding\Conversation\StateMachine;

final class StateMachineEngine
{
    public function transition(ConversationState $from, ConversationState $to, string $reason): StateTransition
    {
        if ($from->isTerminal() && $from !== $to) {
            throw new \InvalidArgumentException("Cannot transition from terminal state {$from->value}.");
        }

        return new StateTransition($from, $to, $reason);
    }
}
