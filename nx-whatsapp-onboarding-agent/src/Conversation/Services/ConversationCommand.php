<?php

declare(strict_types=1);

namespace NxTutors\WhatsAppOnboarding\Conversation\Services;

enum ConversationCommand: string
{
    case Signup = 'signup';
    case Student = 'student';
    case Tutor = 'tutor';
    case Back = 'back';
    case Skip = 'skip';
    case Restart = 'restart';
    case Cancel = 'cancel';
    case Help = 'help';
    case Human = 'human';
    case Review = 'review';
    case Edit = 'edit';
    case Confirm = 'confirm';
    case Agree = 'agree';
    case Unknown = 'unknown';
}
