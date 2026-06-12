<?php

declare(strict_types=1);

namespace NxTutors\WhatsAppOnboarding\Conversation\Services;

final readonly class CommandDetector
{
    public function __construct(private InputNormalizer $normalizer)
    {
    }

    /** @return array{command:ConversationCommand,argument:?string} */
    public function detect(?string $text): array
    {
        $normalized = $this->normalizer->normalize($text);

        if ($normalized === 'back') {
            return ['command' => ConversationCommand::Back, 'argument' => null];
        }

        if ($normalized === 'skip') {
            return ['command' => ConversationCommand::Skip, 'argument' => null];
        }

        if ($normalized === 'restart') {
            return ['command' => ConversationCommand::Restart, 'argument' => null];
        }

        if ($normalized === 'cancel') {
            return ['command' => ConversationCommand::Cancel, 'argument' => null];
        }

        if ($normalized === 'help') {
            return ['command' => ConversationCommand::Help, 'argument' => null];
        }

        if (in_array($normalized, ['human', 'agent', 'support'], true)) {
            return ['command' => ConversationCommand::Human, 'argument' => null];
        }

        if ($normalized === 'review') {
            return ['command' => ConversationCommand::Review, 'argument' => null];
        }

        if (preg_match('/^edit\s+([a-z_ ]{2,40})$/', $normalized, $matches) === 1) {
            return ['command' => ConversationCommand::Edit, 'argument' => str_replace(' ', '_', trim($matches[1]))];
        }

        if (in_array($normalized, ['confirm', 'yes', 'y'], true)) {
            return ['command' => ConversationCommand::Confirm, 'argument' => null];
        }

        if (in_array($normalized, ['i agree', 'agree', 'yes i agree'], true)) {
            return ['command' => ConversationCommand::Agree, 'argument' => null];
        }

        if ($normalized === '1' || $normalized === 'student') {
            return ['command' => ConversationCommand::Student, 'argument' => null];
        }

        if ($normalized === '2' || $normalized === 'tutor' || $normalized === 'teacher') {
            return ['command' => ConversationCommand::Tutor, 'argument' => null];
        }

        if (str_contains($normalized, 'signup') || str_contains($normalized, 'sign up') || str_contains($normalized, 'register')) {
            return ['command' => ConversationCommand::Signup, 'argument' => null];
        }

        return ['command' => ConversationCommand::Unknown, 'argument' => null];
    }
}
