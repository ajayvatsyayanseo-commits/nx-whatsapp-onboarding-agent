<?php

declare(strict_types=1);

namespace NxTutors\WhatsAppOnboarding\Profile\Services;

use NxTutors\WhatsAppOnboarding\Profile\Models\OnboardingConversation;
use NxTutors\WhatsAppOnboarding\Profile\Repositories\RegisterRepository;
use NxTutors\WhatsAppOnboarding\Contracts\MessageSenderInterface;

final readonly class ExistingAccountService
{
    public function __construct(
        private RegisterRepository $registers,
        private MessageSenderInterface $messageSender,
        private DashboardLinkService $dashboardLinks,
    ) {
    }

    public function handleExistingAccount(OnboardingConversation $conversation): array
    {
        $context = $conversation->context ?? [];
        $phone = $context['phone'] ?? $conversation->wa_phone;
        $email = $context['email'] ?? null;

        $existingAccount = $this->registers->findByPhone($phone);

        if (! $existingAccount) {
            return [
                'found' => false,
                'error' => 'Account not found',
            ];
        }

        $this->sendExistingAccountMessage($conversation->wa_phone, $existingAccount->email);

        return [
            'found' => true,
            'user_id' => $existingAccount->user_id,
            'email' => $existingAccount->email,
            'phone' => $existingAccount->phone,
            'name' => $existingAccount->name,
            'role' => $conversation->role,
            'message_sent' => true,
        ];
    }

    public function handleHumanRequest(OnboardingConversation $conversation, string $userInput): array
    {
        $context = $conversation->context ?? [];
        $phone = $context['phone'] ?? $conversation->wa_phone;

        $existingAccount = $this->registers->findByPhone($phone);

        if (! $existingAccount) {
            $this->messageSender->sendText(
                $conversation->wa_phone,
                "I couldn't find your account. Please reply with:\n\n📧 Your email address\nOR\n📱 Your user ID\n\nThis will help me locate your account.",
                ['event_type' => 'account_verification_request']
            );

            return [
                'success' => false,
                'error' => 'Account not found',
            ];
        }

        $this->sendDashboardOptions($conversation->wa_phone, $existingAccount);

        return [
            'success' => true,
            'message' => 'Sent dashboard options',
            'user_id' => $existingAccount->user_id,
        ];
    }

    public function handleDashboardChoice(OnboardingConversation $conversation, string $choice): array
    {
        $context = $conversation->context ?? [];
        $phone = $context['phone'] ?? $conversation->wa_phone;

        $existingAccount = $this->registers->findByPhone($phone);

        if (! $existingAccount) {
            return [
                'success' => false,
                'error' => 'Account not found',
            ];
        }

        $choice = strtolower(trim($choice));

        if ($choice === '1' || strpos($choice, 'login') !== false) {
            return $this->sendDashboardLink($conversation->wa_phone, $existingAccount);
        } elseif ($choice === '2' || strpos($choice, 'support') !== false) {
            return $this->sendSupportMessage($conversation->wa_phone, $existingAccount);
        } else {
            $this->messageSender->sendText(
                $conversation->wa_phone,
                "Please reply with:\n\n*1* - Login to Dashboard\n*2* - Contact Support",
                ['event_type' => 'dashboard_choice_invalid']
            );

            return [
                'success' => false,
                'error' => 'Invalid choice',
            ];
        }
    }

    private function sendExistingAccountMessage(string $toPhone, string $email): void
    {
        $message = "👋 *Welcome back!*\n\n";
        $message .= "We found an existing account with your phone number.\n\n";
        $message .= "📧 *Email:* $email\n\n";
        $message .= "Is this your account?\n\n";
        $message .= "Reply with:\n";
        $message .= "*YES* - If this is your account\n";
        $message .= "*HUMAN* - To speak with our team\n";
        $message .= "*NO* - If this is not your account";

        $this->messageSender->sendText($toPhone, $message, ['event_type' => 'existing_account_detected']);
    }

    private function sendDashboardOptions(string $toPhone, $account): void
    {
        $message = "🔐 *Your Account Details*\n\n";
        $message .= "👤 *Name:* " . $account->name . "\n";
        $message .= "📧 *Email:* " . $account->email . "\n";
        $message .= "🆔 *User ID:* " . $account->user_id . "\n\n";
        $message .= "What would you like to do?\n\n";
        $message .= "Reply with:\n";
        $message .= "*1* - 🔗 Login to Dashboard\n";
        $message .= "*2* - 📞 Contact Support Team";

        $this->messageSender->sendText($toPhone, $message, ['event_type' => 'dashboard_options_sent']);
    }

    private function sendDashboardLink(string $toPhone, $account): array
    {
        $dashboardLink = $this->dashboardLinks->dashboardForRole($account->user_type, $account);

        $message = "✅ *Dashboard Access*\n\n";
        $message .= "🔗 *Your Dashboard Link:*\n";
        $message .= $dashboardLink . "\n\n";
        $message .= "📌 *Instructions:*\n";
        $message .= "1️⃣ Click the link above\n";
        $message .= "2️⃣ You'll be automatically logged in\n";
        $message .= "3️⃣ Complete your profile setup\n\n";
        $message .= "❓ *Need help?*\n";
        $message .= "Reply with 'SUPPORT' to chat with our team.";

        $this->messageSender->sendText($toPhone, $message, ['event_type' => 'dashboard_link_sent']);

        return [
            'success' => true,
            'action' => 'dashboard_link_sent',
            'dashboard_link' => $dashboardLink,
            'user_id' => $account->user_id,
        ];
    }

    private function sendSupportMessage(string $toPhone, $account): array
    {
        $message = "📞 *Support Team Available*\n\n";
        $message .= "Thank you for contacting us!\n\n";
        $message .= "🆔 *Your User ID:* " . $account->user_id . "\n";
        $message .= "📧 *Email:* " . $account->email . "\n";
        $message .= "👤 *Name:* " . $account->name . "\n\n";
        $message .= "Our support team will help you shortly.\n\n";
        $message .= "📧 *You can also email us:*\n";
        $message .= "support@nxtutors.com\n\n";
        $message .= "⏱️ *Response Time:* Usually within 2-4 hours\n";
        $message .= "🕐 *Hours:* Monday-Friday, 9AM-6PM";

        $this->messageSender->sendText($toPhone, $message, ['event_type' => 'support_message_sent']);

        return [
            'success' => true,
            'action' => 'support_message_sent',
            'user_id' => $account->user_id,
        ];
    }
}
