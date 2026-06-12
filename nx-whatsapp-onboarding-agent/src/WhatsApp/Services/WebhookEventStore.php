<?php

declare(strict_types=1);

namespace NxTutors\WhatsAppOnboarding\WhatsApp\Services;

use Illuminate\Database\QueryException;
use NxTutors\WhatsAppOnboarding\Profile\Models\OnboardingEvent;
use NxTutors\WhatsAppOnboarding\WhatsApp\DTO\InboundWhatsAppMessage;

final class WebhookEventStore
{
    /** @param array<string, mixed> $payload */
    public function store(InboundWhatsAppMessage $message, array $payload): OnboardingEvent
    {
        $timestamp = $message->receivedAt !== null ? \DateTimeImmutable::createFromFormat(\DateTimeInterface::ATOM, $message->receivedAt) : false;
        $idempotencyKey = hash('sha256', $message->messageId . '|' . $message->fromPhone . '|' . ($message->receivedAt ?? ''));

        try {
            return OnboardingEvent::query()->create([
                'wa_message_id' => $message->messageId,
                'wa_phone' => $message->fromPhone,
                'idempotency_key' => $idempotencyKey,
                'direction' => 'inbound',
                'event_type' => $message->type,
                'payload' => $payload,
                'status' => 'queued',
                'webhook_timestamp' => $timestamp !== false ? $timestamp->format('Y-m-d H:i:sP') : null,
                'received_at' => now(),
            ]);
        } catch (QueryException) {
            return OnboardingEvent::query()
                ->where('idempotency_key', $idempotencyKey)
                ->orWhere('wa_message_id', $message->messageId)
                ->firstOrFail();
        }
    }
}
