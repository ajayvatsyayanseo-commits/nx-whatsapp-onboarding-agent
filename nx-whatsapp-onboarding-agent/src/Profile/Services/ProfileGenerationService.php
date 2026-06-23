<?php

declare(strict_types=1);

namespace NxTutors\WhatsAppOnboarding\Profile\Services;

use NxTutors\WhatsAppOnboarding\Profile\Models\OnboardingConversation;
use NxTutors\WhatsAppOnboarding\Profile\DTO\ProfileCreationCommand;
use NxTutors\WhatsAppOnboarding\Profile\DTO\ProfileWriteResult;
use NxTutors\WhatsAppOnboarding\Contracts\MessageSenderInterface;
use NxTutors\WhatsAppOnboarding\Tutor\Services\TutorProfileAssembler;
use NxTutors\WhatsAppOnboarding\Student\Services\StudentProfileAssembler;
use RuntimeException;

final readonly class ProfileGenerationService
{
    public function __construct(
        private ProfileCreationDispatcher $dispatcher,
        private MessageSenderInterface $messageSender,
        private DashboardLinkService $dashboardLinks,
        private TutorProfileAssembler $tutorAssembler,
    ) {
    }

    public function generateProfileAndNotify(int $conversationId, string $role): array
    {
        $conversation = OnboardingConversation::query()->findOrFail($conversationId);

        if ($conversation->role !== $role) {
            throw new RuntimeException('Conversation role mismatch.');
        }

        try {
            $command = new ProfileCreationCommand($conversationId, $role);
            $result = $this->dispatcher->dispatchNow($command);

            $profileDetails = $this->formatProfileMessage($result, $role, $conversation);

            $this->messageSender->sendText(
                $conversation->wa_phone,
                $profileDetails['message'],
                ['event_type' => 'profile_generated']
            );

            return [
                'success' => true,
                'user_id' => $result->register->user_id,
                'email' => $result->register->email,
                'phone' => $result->register->phone,
                'temporary_password' => $result->temporaryPassword,
                'dashboard_link' => $this->dashboardLinks->dashboardForRole($role, $result->register),
                'message_sent' => true,
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    private function formatProfileMessage(ProfileWriteResult $result, string $role, OnboardingConversation $conversation): array
    {
        $register = $result->register;
        $tempPassword = $result->temporaryPassword;
        $dashboardLink = $this->dashboardLinks->dashboardForRole($role, $register);

        $message = "✅ *Profile Generated Successfully!*\n\n";
        $message .= "👤 *Name:* {$register->name}\n";
        $message .= "📧 *Email:* {$register->email}\n";
        $message .= "📱 *Phone:* {$register->phone}\n";
        $message .= "🆔 *User ID:* {$register->user_id}\n";
        $message .= "🔐 *Temporary Password:* {$tempPassword}\n\n";

        if ($role === 'tutor') {
            $message .= "📚 *Subjects:* {$register->for_class}\n";
            $message .= "💼 *Experience:* {$register->experience} years\n";
            $message .= "📍 *City:* {$register->city}\n\n";
        } elseif ($role === 'student') {
            $message .= "📚 *Class:* {$register->class_type}\n";
            $message .= "📍 *City:* {$register->city}\n\n";
        }

        $message .= "🔗 *Dashboard Link:* {$dashboardLink}\n\n";
        $message .= "⚠️ Please change your password after login for security.\n";
        $message .= "For support, reply to this message or visit our website.";

        return [
            'message' => $message,
            'user_id' => $register->user_id,
            'dashboard_link' => $dashboardLink,
        ];
    }
}
