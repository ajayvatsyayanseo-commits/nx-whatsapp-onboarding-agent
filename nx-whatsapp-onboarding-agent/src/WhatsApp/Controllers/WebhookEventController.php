<?php

declare(strict_types=1);

namespace NxTutors\WhatsAppOnboarding\WhatsApp\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
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
            return response()->json(['status' => 'error', 'reason' => 'payload_too_large'], 413);
        }

        $payload = $request->json()->all();
        if ($this->isLeadIntakeHandoff($request, $payload)) {
            return $this->handleLeadIntakeHandoff($request, $payload);
        }

        // Not an internal handoff → genuine Meta webhook. Require a valid signature.
        $signature = $request->headers->get((string) config('whatsapp_onboarding_security.webhook.signature_header'));
        if (! $this->signatureVerifier->verify($rawBody, $signature)) {
            return response()->json(['status' => 'forbidden', 'reason' => 'invalid_meta_signature'], 403);
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
        return $request->headers->has((string) config('whatsapp_onboarding.internal_handoff.header', 'X-NXTUTORS-INTERNAL-SECRET'))
            || ($payload['source'] ?? null) === (string) config('whatsapp_onboarding.internal_handoff.source', 'lead_intake_agent');
    }

    /** @param array<string, mixed> $payload */
    private function handleLeadIntakeHandoff(Request $request, array $payload): JsonResponse
    {
        $correlationId = (string) ($request->headers->get('X-Correlation-Id') ?: $request->headers->get('X-Request-Id') ?: bin2hex(random_bytes(8)));

        // Secrets are read from config (not env() at call time) so they survive
        // `php artisan config:cache` in production.
        $configuredSecret = (string) config('whatsapp_onboarding.internal_handoff.secret', '');
        $providedSecret = (string) $request->headers->get(
            (string) config('whatsapp_onboarding.internal_handoff.header', 'X-NXTUTORS-INTERNAL-SECRET'),
            ''
        );

        // (Req 3) Server-side secret missing. Never silently accept a handoff.
        if ($configuredSecret === '') {
            Log::warning('lead_intake_handoff_misconfigured', [
                'correlation_id' => $correlationId,
                'mode' => 'lead_intake_handoff',
                'internal_secret_valid' => false,
                'reason' => 'server_secret_not_configured',
            ]);

            if (app()->environment('production')) {
                return response()->json([
                    'status' => 'error',
                    'reason' => 'server_internal_secret_not_configured',
                ], 503);
            }

            return response()->json(['status' => 'unauthorized', 'reason' => 'invalid_internal_secret'], 401);
        }

        // (Req 4) Wrong or missing client secret.
        if ($providedSecret === '' || ! hash_equals($configuredSecret, $providedSecret)) {
            Log::warning('lead_intake_handoff_unauthorized', [
                'correlation_id' => $correlationId,
                'mode' => 'lead_intake_handoff',
                'internal_secret_valid' => false,
            ]);

            return response()->json(['status' => 'unauthorized', 'reason' => 'invalid_internal_secret'], 401);
        }

        $messageText = $this->extractText($payload);
        $waPhone = $this->extractPhone($payload);
        $waMessageId = $this->extractMessageId($payload);
        $detectedRole = $this->detectRole($messageText);

        // (Req 10) Idempotency. A duplicate wa_message_id must not restart the
        // flow or trigger a duplicate reply. Cache::add() is atomic; in
        // production this is Redis-backed and works across tasks.
        if ($waMessageId !== '' && ! Cache::add($this->idempotencyKey($waMessageId), 1, now()->addDay())) {
            Log::info('lead_intake_handoff_duplicate', [
                'correlation_id' => $correlationId,
                'wa_message_id' => $waMessageId,
                'wa_phone' => $this->piiMasker->maskValue('phone', $waPhone),
                'source' => 'lead_intake_agent',
                'mode' => 'lead_intake_handoff',
                'internal_secret_valid' => true,
                'detected_role' => $detectedRole,
                'reply_text_present' => false,
                'duplicate' => true,
            ]);

            return response()->json([
                'status' => 'duplicate',
                'mode' => 'lead_intake_handoff',
                'wa_message_id' => $waMessageId,
                'reply_text' => null,
            ]);
        }

        $replyText = $this->replyText($detectedRole);

        // (Req 11) One structured log line per handoff with all required fields.
        Log::info('lead_intake_handoff_accepted', [
            'correlation_id' => $correlationId,
            'wa_message_id' => $waMessageId,
            'wa_phone' => $this->piiMasker->maskValue('phone', $waPhone),
            'source' => 'lead_intake_agent',
            'mode' => 'lead_intake_handoff',
            'internal_secret_valid' => true,
            'detected_role' => $detectedRole,
            'reply_text_present' => $replyText !== '',
            'duplicate' => false,
        ]);

        // (Req 8) Do NOT send WhatsApp here. Return reply_text to lead-intake.
        return response()->json([
            'status' => 'accepted',
            'mode' => 'lead_intake_handoff',
            'wa_message_id' => $waMessageId,
            'wa_phone' => $waPhone,
            'detected_role' => $detectedRole,
            'reply_text' => $replyText,
        ]);
    }

    private function idempotencyKey(string $waMessageId): string
    {
        return 'nxtutors:onboarding:handoff:' . sha1($waMessageId);
    }

    /** @param array<string, mixed> $payload */
    private function extractText(array $payload): string
    {
        $flatText = $this->firstScalar($payload, ['message_text', 'text', 'body']);
        if ($flatText !== '') {
            return $flatText;
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
        $phone = $this->firstScalar($payload, ['wa_phone', 'phone', 'from']);
        if ($phone !== '') {
            return $phone;
        }

        return (string) ($payload['entry'][0]['changes'][0]['value']['messages'][0]['from'] ?? '');
    }

    /** @param array<string, mixed> $payload */
    private function extractMessageId(array $payload): string
    {
        $messageId = $this->firstScalar($payload, ['wa_message_id', 'message_id', 'id']);
        if ($messageId !== '') {
            return $messageId;
        }

        return (string) ($payload['entry'][0]['changes'][0]['value']['messages'][0]['id'] ?? '');
    }

    /**
     * @param array<string, mixed> $payload
     * @param list<string>         $keys
     */
    private function firstScalar(array $payload, array $keys): string
    {
        foreach ($keys as $key) {
            $value = $payload[$key] ?? null;
            if (is_scalar($value) && (string) $value !== '') {
                return (string) $value;
            }
        }

        return '';
    }

    private function detectRole(string $text): string
    {
        $text = trim((string) preg_replace('/\s+/', ' ', strtolower($text)));

        // Explicit keywords take priority over the numbered menu so a phrase like
        // "I want to register as tutor" is never mis-read as a menu number.
        if (str_contains($text, 'tutor') || str_contains($text, 'teacher') || str_contains($text, 'teach')) {
            return 'tutor';
        }

        if (str_contains($text, 'student') || str_contains($text, 'parent') || str_contains($text, 'learner') || str_contains($text, 'study')) {
            return 'student';
        }

        // Numbered menu answers: "1" => Student, "2" => Tutor. Accept a bare number
        // or light decoration ("1", "1.", "1)", "option 1") so a quick reply works.
        if (preg_match('/^(?:option\s*)?1[.):]?$/', $text) === 1) {
            return 'student';
        }

        if (preg_match('/^(?:option\s*)?2[.):]?$/', $text) === 1) {
            return 'tutor';
        }

        return 'unknown';
    }

    private function replyText(string $role): string
    {
        return match ($role) {
            'student' => "Great, let's create your student profile. What is your full name?",
            'tutor' => "Great, let's create your tutor profile. What is your full name?",
            default => "👋 Welcome to NXtutors signup. Are you joining as a:\n1. Student\n2. Tutor\n\nReply 1 or 2 (or type \"student\" / \"tutor\").",
        };
    }
}
