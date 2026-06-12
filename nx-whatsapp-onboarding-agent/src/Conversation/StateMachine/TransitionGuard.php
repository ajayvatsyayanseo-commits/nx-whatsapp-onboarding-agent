<?php

declare(strict_types=1);

namespace NxTutors\WhatsAppOnboarding\Conversation\StateMachine;

final class TransitionGuard
{
    /** @param array<string, list<string>> $allowed */
    public function __construct(private readonly array $allowed)
    {
    }

    public function allows(ConversationState $from, ConversationState $to): bool
    {
        return in_array($to->value, $this->allowed[$from->value] ?? [], true);
    }
}
