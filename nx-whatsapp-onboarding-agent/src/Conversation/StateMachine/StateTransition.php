<?php

declare(strict_types=1);

namespace NxTutors\WhatsAppOnboarding\Conversation\StateMachine;

final readonly class StateTransition
{
    public function __construct(
        public ConversationState $from,
        public ConversationState $to,
        public string $reason,
    ) {
    }
}
