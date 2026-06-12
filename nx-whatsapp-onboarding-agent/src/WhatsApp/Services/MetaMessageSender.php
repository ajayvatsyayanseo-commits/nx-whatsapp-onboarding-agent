<?php

declare(strict_types=1);

namespace NxTutors\WhatsAppOnboarding\WhatsApp\Services;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use NxTutors\WhatsAppOnboarding\Contracts\MessageSenderInterface;
use NxTutors\WhatsAppOnboarding\Observability\Metrics\MetricsRecorder;
use NxTutors\WhatsAppOnboarding\Profile\Models\OnboardingEvent;
use NxTutors\WhatsAppOnboarding\Security\PiiMasking\PiiMasker;
use RuntimeException;

final readonly class MetaMessageSender implements MessageSenderInterface
{
    public function __construct(
        private ClientInterface $http,
        private WhatsAppRateLimiter $rateLimiter,
        private PiiMasker $masker,
        private WhatsAppOptOutService $optOuts,
        private MetaCircuitBreaker $circuitBreaker,
        private MetricsRecorder $metrics,
    ) {
    }

    public function sendText(string $toPhone, string $body, array $metadata = []): void
    {
        $this->send($toPhone, [
            'messaging_product' => 'whatsapp',
            'to' => $toPhone,
            'type' => 'text',
            'text' => ['preview_url' => false, 'body' => $body],
        ], $metadata);
    }

    public function sendTemplate(string $toPhone, string $templateName, string $language, array $parameters = []): void
    {
        $this->send($toPhone, [
            'messaging_product' => 'whatsapp',
            'to' => $toPhone,
            'type' => 'template',
            'template' => [
                'name' => $templateName,
                'language' => ['code' => $language],
                'components' => $parameters,
            ],
        ], ['template_name' => $templateName]);
    }

    public function sendButtons(string $toPhone, string $body, array $buttons, string $fallbackText, array $metadata = []): void
    {
        if (! (bool) config('whatsapp_onboarding.meta.interactive_enabled', true)) {
            $this->sendText($toPhone, $fallbackText, $metadata);
            return;
        }

        $this->send($toPhone, [
            'messaging_product' => 'whatsapp',
            'to' => $toPhone,
            'type' => 'interactive',
            'interactive' => [
                'type' => 'button',
                'body' => ['text' => $body],
                'action' => [
                    'buttons' => array_map(static fn (array $button): array => [
                        'type' => 'reply',
                        'reply' => [
                            'id' => $button['id'],
                            'title' => $button['title'],
                        ],
                    ], array_slice($buttons, 0, 3)),
                ],
            ],
        ], $metadata);
    }

    /** @param array<string, mixed> $payload */
    private function send(string $toPhone, array $payload, array $metadata = []): void
    {
        if ((bool) config('whatsapp_onboarding_security.pause.outbound_paused', false)) {
            throw new RuntimeException('WhatsApp outbound messages are paused.');
        }

        if ($this->optOuts->isOptedOut($toPhone) && ($payload['type'] ?? null) !== 'template') {
            throw new RuntimeException('Recipient opted out of non-template WhatsApp messages.');
        }

        if (($metadata['outside_session'] ?? false) === true && (bool) config('whatsapp_onboarding.meta.require_template_outside_session', true) && ($payload['type'] ?? null) !== 'template') {
            throw new RuntimeException('Approved WhatsApp template is required outside the active user-initiated conversation.');
        }

        $this->circuitBreaker->assertAvailable();
        $this->rateLimiter->assertCanSend($toPhone);
        $outboundEvent = $this->storeOutboundEvent($toPhone, $payload);

        $phoneNumberId = (string) config('whatsapp_onboarding.meta.phone_number_id', '');
        $accessToken = (string) config('whatsapp_onboarding.meta.access_token', '');
        if ($phoneNumberId === '' || $accessToken === '') {
            throw new RuntimeException('Meta WhatsApp credentials are not configured.');
        }

        $baseUrl = rtrim((string) config('whatsapp_onboarding.meta.graph_base_url'), '/');
        $version = trim((string) config('whatsapp_onboarding.meta.api_version'), '/');

        $lastException = null;
        for ($attempt = 1; $attempt <= 3; $attempt++) {
            try {
                $started = microtime(true);
                $this->http->request('POST', "{$baseUrl}/{$version}/{$phoneNumberId}/messages", [
                    'headers' => ['Authorization' => "Bearer {$accessToken}"],
                    'json' => $payload,
                    'timeout' => 5,
                ]);

                $outboundEvent->forceFill(['status' => 'provider_accepted', 'processed_at' => now()])->save();
                $this->circuitBreaker->recordSuccess();
                $this->metrics->increment('whatsapp_message_sent_count', ['type' => (string) ($payload['type'] ?? 'unknown')]);
                $this->metrics->timing('meta_api_latency_ms', (int) ((microtime(true) - $started) * 1000));
                return;
            } catch (GuzzleException $exception) {
                $lastException = $exception;
                $statusCode = $exception instanceof RequestException && $exception->hasResponse()
                    ? $exception->getResponse()?->getStatusCode()
                    : null;
                $this->circuitBreaker->recordFailure($statusCode);
                $this->metrics->increment('meta_api_error_count', ['status' => $statusCode]);
                if ($attempt < 3) {
                    usleep((int) ((100000 * (2 ** ($attempt - 1))) + random_int(0, 100000)));
                }
            }
        }

        $outboundEvent->forceFill(['status' => 'provider_failed', 'processed_at' => now()])->save();
        throw new RuntimeException('Unable to send WhatsApp message.', 0, $lastException);
    }

    /** @param array<string, mixed> $payload */
    private function storeOutboundEvent(string $toPhone, array $payload): OnboardingEvent
    {
        return OnboardingEvent::query()->create([
            'wa_phone' => $toPhone,
            'idempotency_key' => hash('sha256', 'outbound|' . $toPhone . '|' . json_encode($payload) . '|' . microtime(true)),
            'direction' => 'outbound',
            'event_type' => (string) ($payload['type'] ?? 'unknown'),
            'payload' => $this->masker->maskArray($payload),
            'status' => 'queued_for_provider',
            'received_at' => now(),
        ]);
    }
}
