<?php

declare(strict_types=1);

namespace NxTutors\WhatsAppOnboarding\Privacy;

use NxTutors\WhatsAppOnboarding\Profile\Models\OnboardingConversation;
use NxTutors\WhatsAppOnboarding\Profile\Models\OnboardingEvent;
use NxTutors\WhatsAppOnboarding\Profile\Models\HumanHandoffTicket;

final class DataDeletionService
{
    public function anonymizePhone(string $phone): int
    {
        $hash = hash_hmac('sha256', $phone, (string) config('app.key', 'local-testing-key'));
        $updated = OnboardingConversation::query()
            ->where('wa_phone', $phone)
            ->update(['wa_phone' => 'anon:' . $hash, 'context' => []]);

        OnboardingEvent::query()
            ->where('wa_phone', $phone)
            ->update(['wa_phone' => 'anon:' . $hash, 'payload' => ['privacy_deleted' => true]]);

        HumanHandoffTicket::query()
            ->where('wa_phone', $phone)
            ->update(['wa_phone' => 'anon:' . $hash]);

        return $updated;
    }
}
