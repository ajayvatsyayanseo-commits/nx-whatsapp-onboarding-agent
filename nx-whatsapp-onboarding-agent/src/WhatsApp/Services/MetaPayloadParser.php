<?php

declare(strict_types=1);

namespace NxTutors\WhatsAppOnboarding\WhatsApp\Services;

use NxTutors\WhatsAppOnboarding\WhatsApp\DTO\InboundWhatsAppMessage;

final class MetaPayloadParser
{
    /** @param array<string, mixed> $payload */
    public function parseFirstMessage(array $payload): ?InboundWhatsAppMessage
    {
        $value = $payload['entry'][0]['changes'][0]['value'] ?? null;
        if (! is_array($value)) {
            return null;
        }

        $message = $value['messages'][0] ?? null;
        if (! is_array($message)) {
            return null;
        }

        $type = (string) ($message['type'] ?? 'unknown');
        $text = null;
        if ($type === 'text') {
            $text = (string) ($message['text']['body'] ?? '');
        } elseif ($type === 'button') {
            $text = (string) ($message['button']['text'] ?? '');
        } elseif ($type === 'interactive') {
            $text = (string) (
                $message['interactive']['button_reply']['title']
                ?? $message['interactive']['list_reply']['title']
                ?? ''
            );
        }

        return new InboundWhatsAppMessage(
            messageId: (string) ($message['id'] ?? ''),
            fromPhone: (string) ($message['from'] ?? ''),
            text: $text,
            type: $type,
            raw: $message,
            receivedAt: isset($message['timestamp']) ? date('c', (int) $message['timestamp']) : null,
        );
    }
}
