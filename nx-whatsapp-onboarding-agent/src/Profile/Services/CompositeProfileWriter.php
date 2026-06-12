<?php

declare(strict_types=1);

namespace NxTutors\WhatsAppOnboarding\Profile\Services;

use NxTutors\WhatsAppOnboarding\Contracts\ProfileWriterInterface;
use NxTutors\WhatsAppOnboarding\Profile\DTO\ProfileWriteResult;
use NxTutors\WhatsAppOnboarding\Profile\Models\OnboardingConversation;
use NxTutors\WhatsAppOnboarding\Student\Services\StudentProfileWriter;
use NxTutors\WhatsAppOnboarding\Tutor\Services\TutorProfileWriter;
use RuntimeException;

final readonly class CompositeProfileWriter implements ProfileWriterInterface
{
    public function __construct(
        private StudentProfileWriter $studentWriter,
        private TutorProfileWriter $tutorWriter,
    ) {
    }

    public function write(OnboardingConversation $conversation): ProfileWriteResult
    {
        return match ($conversation->role) {
            'student' => $this->studentWriter->write($conversation),
            'tutor' => $this->tutorWriter->write($conversation),
            default => throw new RuntimeException('Conversation role is required before profile creation.'),
        };
    }
}
