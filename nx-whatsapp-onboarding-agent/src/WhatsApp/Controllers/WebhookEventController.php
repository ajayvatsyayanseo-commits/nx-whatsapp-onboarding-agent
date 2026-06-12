<?php

declare(strict_types=1);

namespace NxTutors\WhatsAppOnboarding\WhatsApp\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use NxTutors\WhatsAppOnboarding\Contracts\PolicyGuardInterface;
use NxTutors\WhatsAppOnboarding\WhatsApp\Jobs\ProcessInboundWhatsAppEventJob;
use NxTutors\WhatsAppOnboarding\WhatsApp\Services\MetaPayloadParser;
use NxTutors\WhatsAppOnboarding\WhatsApp\Services\MetaWebhookSignatureVerifier;
use NxTutors\WhatsAppOnboarding\WhatsApp\Services\WebhookEventStore;
use RuntimeException;

final readonly class WebhookEventController
{
    public function __construct(
        private MetaWebhookSignatureVerifier $signatureVerifier,
        private MetaPayloadParser $payloadParser,
        private WebhookEventStore $eventStore,
        private PolicyGuardInterface $policyGuard,
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $rawBody = $request->getContent();
        if (strlen($rawBody) > (int) config('whatsapp_onboarding_security.webhook.max_body_bytes', 262144)) {
            return response()->json(['ok' => false], 413);
        }

        $signature = $request->headers->get((string) config('whatsapp_onboarding_security.webhook.signature_header'));

        if (! $this->signatureVerifier->verify($rawBody, $signature)) {
            return response()->json(['ok' => false], 403);
        }

        $payload = $request->json()->all();
        $payload['_request_metadata'] = [
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ];
        $message = $this->payloadParser->parseFirstMessage($payload);
        if ($message === null || $message->messageId === '' || $message->fromPhone === '') {
            return response()->json(['ok' => true]);
        }

        try {
            $this->policyGuard->assertCanStart($message->text);
        } catch (RuntimeException) {
            return response()->json(['ok' => true, 'queued' => false]);
        }

        $event = $this->eventStore->store($message, $payload);
        if ($event->wasRecentlyCreated) {
            ProcessInboundWhatsAppEventJob::dispatch($event->id)
                ->onQueue((string) config('whatsapp_onboarding.queue'));
        }

        return response()->json(['ok' => true]);
    }
}
