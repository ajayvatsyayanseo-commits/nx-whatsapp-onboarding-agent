<?php

declare(strict_types=1);

namespace NxTutors\WhatsAppOnboarding\WhatsApp\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use NxTutors\WhatsAppOnboarding\Contracts\PolicyGuardInterface;
use NxTutors\WhatsAppOnboarding\Security\PiiMasking\PiiMasker;
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
        private PiiMasker $piiMasker,
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $rawBody = $request->getContent();
        if (strlen($rawBody) > (int) config('whatsapp_onboarding_security.webhook.max_body_bytes', 262144)) {
            return response()->json(['ok' => false], 413);
        }

        $payload = $request->json()->all();
        if ($this->isLeadIntakeHandoff($request, $payload)) {
            return $this->handleLeadIntakeHandoff($request, $payload);
        }

        $signature = $request->headers->get((string) config('whatsapp_onboarding_security.webhook.signature_header'));

        if (! $this->signatureVerifier->verify($rawBody, $signature)) {
            return response()->json(['ok' => false], 403);
        }

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

    /** @param array<string, mixed> $payload */
    private function isLeadIntakeHandoff(Request $request, array $payload): bool
    {
        return $request->headers->has('X-NXTUTORS-INTERNAL-SECRET')
            || ($payload['source'] ?? null) === 'lead_intake_agent';
    }

    /** @param array<string, mixed> $payload */
    private function handleLeadIntakeHandoff(Request $request, array $payload): JsonResponse
    {
        $configuredSecret = (string) env('ONBOARDING_AGENT_INTERNAL_SECRET', '');
        $providedSecret = (string) $request->headers->get('X-NXTUTORS-INTERNAL-SECRET', '');

        if ($configuredSecret === '' || ! hash_equals($configuredSecret, $providedSecret)) {
            return response()->json(['error' => 'invalid internal secret'], 401);
        }

        $messageText = $this->extractText($payload);
        $role = $this->requestedSignupRole($messageText);

        Log::info('lead_intake_handoff_received', [
            'request_id' => (string) $request->headers->get('X-Request-Id', ''),
            'wa_message_id' => $this->extractMessageId($payload),
            'wa_phone' => $this->piiMasker->maskValue('phone', $this->extractPhone($payload)),
        ]);

        if (! $this->isSignupIntent($messageText)) {
            return response()->json(['status' => 'ignored', 'reason' => 'not_signup_intent'], 202);
        }

        return response()->json([
            'status' => 'accepted',
            'mode' => 'lead_intake_handoff',
            'reply_text' => match ($role) {
                'student' => "Great, let's create your student profile. What is your full name?",
                'tutor' => "Great, let's create your tutor profile. What is your full name?",
                default => 'Welcome to NXtutors signup. Are you joining as a student or tutor?',
            },
        ], 202);
    }

    /** @param array<string, mixed> $payload */
    private function extractText(array $payload): string
    {
        $flatText = $payload['message_text'] ?? $payload['text'] ?? null;
        if (is_scalar($flatText) && (string) $flatText !== '') {
            return (string) $flatText;
        }

        $message = $payload['entry'][0]['changes'][0]['value']['messages'][0] ?? null;
        if (! is_array($message)) {
            return '';
        }

        return (string) (
            $message['text']['body']
            ?? $message['button']['text']
            ?? $message['interactive']['button_reply']['title']
            ?? $message['interactive']['list_reply']['title']
            ?? ''
        );
    }

    /** @param array<string, mixed> $payload */
    private function extractPhone(array $payload): string
    {
        $phone = $payload['wa_phone'] ?? null;
        if (is_scalar($phone) && (string) $phone !== '') {
            return (string) $phone;
        }

        return (string) ($payload['entry'][0]['changes'][0]['value']['messages'][0]['from'] ?? '');
    }

    /** @param array<string, mixed> $payload */
    private function extractMessageId(array $payload): string
    {
        $messageId = $payload['wa_message_id'] ?? null;
        if (is_scalar($messageId) && (string) $messageId !== '') {
            return (string) $messageId;
        }

        return (string) ($payload['entry'][0]['changes'][0]['value']['messages'][0]['id'] ?? '');
    }

    private function isSignupIntent(string $text): bool
    {
        $text = trim((string) preg_replace('/\s+/', ' ', strtolower($text)));

        foreach (['signup', 'sign up', 'register', 'registration', 'create account'] as $keyword) {
            if ($text === $keyword || str_contains($text, $keyword)) {
                return true;
            }
        }

        return false;
    }

    private function requestedSignupRole(string $text): ?string
    {
        $text = strtolower($text);
        if (str_contains($text, 'tutor') || str_contains($text, 'teacher')) {
            return 'tutor';
        }

        if (str_contains($text, 'student')) {
            return 'student';
        }

        return null;
    }
}
