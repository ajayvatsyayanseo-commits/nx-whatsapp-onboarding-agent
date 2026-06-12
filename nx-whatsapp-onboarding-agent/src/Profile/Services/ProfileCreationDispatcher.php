<?php

declare(strict_types=1);

namespace NxTutors\WhatsAppOnboarding\Profile\Services;

use NxTutors\WhatsAppOnboarding\Contracts\ProfileWriterInterface;
use NxTutors\WhatsAppOnboarding\Profile\DTO\ProfileCreationCommand;
use NxTutors\WhatsAppOnboarding\Profile\DTO\ProfileWriteResult;
use NxTutors\WhatsAppOnboarding\Profile\Models\OnboardingConversation;

final readonly class ProfileCreationDispatcher
{
    public function __construct(private ProfileWriterInterface $writer)
    {
    }

    public function dispatchNow(ProfileCreationCommand $command): ProfileWriteResult
    {
        $conversation = OnboardingConversation::query()->findOrFail($command->conversationId);

        return $this->writer->write($conversation);
    }
}
