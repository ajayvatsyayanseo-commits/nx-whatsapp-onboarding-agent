<?php

declare(strict_types=1);

namespace NxTutors\WhatsAppOnboarding\Tests\Feature;

use NxTutors\WhatsAppOnboarding\Tests\TestCase;

/**
 * Contract tests for the real AWS runtime: the standalone public/index.php
 * handler served by nginx + php-fpm. These boot an actual PHP server so the
 * exact wire behaviour (status codes, JSON shapes, headers) is exercised.
 */
final class LeadIntakeWebhookContractTest extends TestCase
{
    private const SECRET = 'test-internal-secret';
    private const APP_SECRET = 'test-meta-app-secret';

    /** @var resource|null */
    private $serverProcess = null;

    private string $webhookBaseUrl = '';

    private string $idempotencyDir = '';

    protected function setUp(): void
    {
        parent::setUp();

        $this->idempotencyDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'nxtutors_test_idemp_' . bin2hex(random_bytes(6));
        @mkdir($this->idempotencyDir, 0700, true);

        [$this->serverProcess, $this->webhookBaseUrl] = $this->bootServer();
    }

    protected function tearDown(): void
    {
        $this->stopServer($this->serverProcess);
        $this->serverProcess = null;

        if ($this->idempotencyDir !== '' && is_dir($this->idempotencyDir)) {
            foreach ((array) glob($this->idempotencyDir . DIRECTORY_SEPARATOR . '*') as $file) {
                @unlink((string) $file);
            }
            @rmdir($this->idempotencyDir);
        }

        parent::tearDown();
    }

    public function testValidSecretReturnsReplyTextQuickly(): void
    {
        $startedAt = microtime(true);
        [$status, $body] = $this->postWebhook(self::SECRET, [
            'wa_message_id' => 'wamid.valid-1',
            'message_text' => 'signup',
        ]);

        self::assertLessThan(2.0, microtime(true) - $startedAt);
        self::assertContains($status, [200, 202]);
        self::assertSame('accepted', $body['status']);
        self::assertSame('lead_intake_handoff', $body['mode']);
        self::assertSame('unknown', $body['detected_role']);
        self::assertSame('wamid.valid-1', $body['wa_message_id']);
        self::assertSame(
            'Welcome to NXtutors signup. Are you joining as a student or tutor?',
            $body['reply_text']
        );
    }

    public function testInvalidSecretReturnsUnauthorized(): void
    {
        [$status, $body] = $this->postWebhook('wrong-secret', [
            'wa_message_id' => 'wamid.invalid-secret',
            'message_text' => 'signup',
        ]);

        self::assertSame(401, $status);
        self::assertSame('unauthorized', $body['status']);
        self::assertSame('invalid_internal_secret', $body['reason']);
        self::assertArrayNotHasKey('reply_text', $body);
    }

    public function testMissingSecretReturnsUnauthorized(): void
    {
        [$status, $body] = $this->postWebhook('', [
            'wa_message_id' => 'wamid.missing-secret',
            'message_text' => 'signup',
        ]);

        self::assertSame(401, $status);
        self::assertSame('unauthorized', $body['status']);
        self::assertSame('invalid_internal_secret', $body['reason']);
        self::assertArrayNotHasKey('reply_text', $body);
    }

    public function testSourceFieldAloneTriggersHandoffPathAndStillRequiresSecret(): void
    {
        // No internal header, but source=lead_intake_agent must still be treated
        // as a handoff and therefore rejected without a valid secret (proving
        // the source field — not just the header — triggers handoff detection).
        [$status, $body] = $this->postWebhook('', [
            'source' => 'lead_intake_agent',
            'wa_message_id' => 'wamid.source-only',
            'message_text' => 'student signup',
        ]);

        self::assertSame(401, $status);
        self::assertSame('invalid_internal_secret', $body['reason']);
    }

    public function testStudentAndTutorSignupStartTheirRoleFlows(): void
    {
        [, $studentBody] = $this->postWebhook(self::SECRET, [
            'wa_message_id' => 'wamid.student-1',
            'message_text' => 'student signup',
        ]);
        [, $tutorBody] = $this->postWebhook(self::SECRET, [
            'wa_message_id' => 'wamid.tutor-1',
            'message_text' => 'tutor signup',
        ]);

        self::assertSame('student', $studentBody['detected_role']);
        self::assertSame("Great, let's create your student profile. What is your full name?", $studentBody['reply_text']);
        self::assertSame('tutor', $tutorBody['detected_role']);
        self::assertSame("Great, let's create your tutor profile. What is your full name?", $tutorBody['reply_text']);
    }

    public function testDuplicateMessageIdIsNotProcessedTwice(): void
    {
        [$firstStatus, $firstBody] = $this->postWebhook(self::SECRET, [
            'wa_message_id' => 'wamid.dup-1',
            'message_text' => 'tutor signup',
        ]);
        [$secondStatus, $secondBody] = $this->postWebhook(self::SECRET, [
            'wa_message_id' => 'wamid.dup-1',
            'message_text' => 'tutor signup',
        ]);

        self::assertContains($firstStatus, [200, 202]);
        self::assertSame('accepted', $firstBody['status']);
        self::assertNotEmpty($firstBody['reply_text']);

        self::assertSame(200, $secondStatus);
        self::assertSame('duplicate', $secondBody['status']);
        self::assertSame('lead_intake_handoff', $secondBody['mode']);
        self::assertNull($secondBody['reply_text']);
    }

    public function testHandoffReturnsReplyTextButDoesNotDirectlySend(): void
    {
        [, $body] = $this->postWebhook(self::SECRET, [
            'wa_message_id' => 'wamid.no-send',
            'message_text' => 'student signup',
        ]);

        // reply_text is returned for lead-intake to send; onboarding must not
        // report having sent a WhatsApp message itself.
        self::assertArrayHasKey('reply_text', $body);
        self::assertNotEmpty($body['reply_text']);
        self::assertArrayNotHasKey('whatsapp_sent', $body);
        self::assertArrayNotHasKey('delivery', $body);
        self::assertArrayNotHasKey('message_sent', $body);
    }

    public function testFieldAliasesAreNormalized(): void
    {
        // Uses the alias set: from / body / id instead of wa_phone / message_text / wa_message_id.
        [$status, $body] = $this->httpPost($this->webhookBaseUrl, '/whatsapp/onboarding/webhook', [
            'source' => 'lead_intake_agent',
            'from' => '919876500000',
            'body' => 'I want to register as tutor',
            'id' => 'wamid.alias-1',
        ], ['X-NXTUTORS-INTERNAL-SECRET: ' . self::SECRET]);

        self::assertContains($status, [200, 202]);
        self::assertSame('accepted', $body['status']);
        self::assertSame('wamid.alias-1', $body['wa_message_id']);
        self::assertSame('919876500000', $body['wa_phone']);
        self::assertSame('tutor', $body['detected_role']);
    }

    public function testIndexPhpAliasReturnsReplyTextForLegacyForwarders(): void
    {
        [$status, $body] = $this->postWebhook(self::SECRET, [
            'wa_message_id' => 'wamid.legacy-alias',
            'message_text' => 'signup',
        ], '/index.php');

        self::assertContains($status, [200, 202]);
        self::assertSame('accepted', $body['status']);
        self::assertSame(
            'Welcome to NXtutors signup. Are you joining as a student or tutor?',
            $body['reply_text']
        );
    }

    public function testOriginalMetaPayloadCanBeForwardedByLeadIntake(): void
    {
        [$status, $body] = $this->httpPost($this->webhookBaseUrl, '/whatsapp/onboarding/webhook', [
            'source' => 'lead_intake_agent',
            'entry' => [[
                'changes' => [[
                    'value' => [
                        'messages' => [[
                            'id' => 'wamid.forwarded',
                            'from' => '919999999999',
                            'timestamp' => (string) time(),
                            'type' => 'text',
                            'text' => ['body' => 'signup'],
                        ]],
                    ],
                ]],
            ]],
        ], ['X-NXTUTORS-INTERNAL-SECRET: ' . self::SECRET]);

        self::assertContains($status, [200, 202]);
        self::assertSame('accepted', $body['status']);
        self::assertSame('wamid.forwarded', $body['wa_message_id']);
        self::assertSame(
            'Welcome to NXtutors signup. Are you joining as a student or tutor?',
            $body['reply_text']
        );
    }

    public function testMissingServerSecretInProductionReturns503(): void
    {
        // Boot a server in production with NO server-side internal secret.
        [$proc, $baseUrl] = $this->bootServer([
            'APP_ENV' => 'production',
            'ONBOARDING_AGENT_INTERNAL_SECRET' => null,
        ]);

        try {
            [$status, $body] = $this->httpPost($baseUrl, '/whatsapp/onboarding/webhook', [
                'source' => 'lead_intake_agent',
                'wa_message_id' => 'wamid.prod-misconfig',
                'message_text' => 'signup',
            ], ['X-NXTUTORS-INTERNAL-SECRET: anything']);

            self::assertSame(503, $status);
            self::assertSame('error', $body['status']);
            self::assertSame('server_internal_secret_not_configured', $body['reason']);
        } finally {
            $this->stopServer($proc);
        }
    }

    public function testGenuineMetaWebhookRequiresValidSignature(): void
    {
        $payload = [
            'object' => 'whatsapp_business_account',
            'entry' => [[
                'changes' => [[
                    'value' => [
                        'messages' => [[
                            'id' => 'wamid.meta-direct',
                            'from' => '919999999999',
                            'timestamp' => (string) time(),
                            'type' => 'text',
                            'text' => ['body' => 'hello'],
                        ]],
                    ],
                ]],
            ]],
        ];
        $json = json_encode($payload, JSON_UNESCAPED_SLASHES);

        // No source field and no internal header → treated as a direct Meta
        // webhook. An invalid/missing signature must be rejected with 403.
        [$badStatus] = $this->httpPostRaw($this->webhookBaseUrl, '/whatsapp/onboarding/webhook', (string) $json, [
            'X-Hub-Signature-256: sha256=deadbeef',
        ]);
        self::assertSame(403, $badStatus);

        // A correctly signed payload is accepted.
        $signature = 'sha256=' . hash_hmac('sha256', (string) $json, self::APP_SECRET);
        [$goodStatus, $goodBody] = $this->httpPostRaw($this->webhookBaseUrl, '/whatsapp/onboarding/webhook', (string) $json, [
            'X-Hub-Signature-256: ' . $signature,
        ]);
        self::assertSame(200, $goodStatus);
        self::assertSame('meta_webhook', $goodBody['mode']);
    }

    public function testInternalHandoffHealthEndpoint(): void
    {
        [$status, $body] = $this->httpGet($this->webhookBaseUrl, '/health/internal-handoff');

        self::assertSame(200, $status);
        self::assertTrue($body['internal_handoff']['onboarding_agent_internal_secret_configured']);
        self::assertTrue($body['internal_handoff']['handoff_route_enabled']);
    }

    /* ---------------------------------------------------------------------- */
    /* Helpers                                                                */
    /* ---------------------------------------------------------------------- */

    /**
     * @param array<string, string|null> $extraEnv  null values unset the variable
     * @return array{0:resource,1:string}
     */
    private function bootServer(array $extraEnv = []): array
    {
        $port = random_int(20000, 45000);
        $baseUrl = "http://127.0.0.1:{$port}";
        $publicPath = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'public';

        $env = [];
        foreach (array_merge($_ENV, $_SERVER) as $key => $value) {
            if (is_scalar($value)) {
                $env[(string) $key] = (string) $value;
            }
        }
        $env['ONBOARDING_AGENT_INTERNAL_SECRET'] = self::SECRET;
        $env['META_WHATSAPP_APP_SECRET'] = self::APP_SECRET;
        $env['ONBOARDING_IDEMPOTENCY_DIR'] = $this->idempotencyDir;

        foreach ($extraEnv as $key => $value) {
            if ($value === null) {
                unset($env[$key]);
            } else {
                $env[$key] = $value;
            }
        }

        $descriptorSpec = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        $process = proc_open(
            sprintf('php -S 127.0.0.1:%d -t %s', $port, escapeshellarg($publicPath)),
            $descriptorSpec,
            $pipes,
            dirname(__DIR__, 3),
            $env
        );

        if (! is_resource($process)) {
            self::fail('Unable to start webhook test server.');
        }

        $deadline = microtime(true) + 3.0;
        do {
            $connection = @fsockopen('127.0.0.1', $port);
            if (is_resource($connection)) {
                fclose($connection);
                return [$process, $baseUrl];
            }
            usleep(25_000);
        } while (microtime(true) < $deadline);

        self::fail('Webhook test server did not start in time.');
    }

    /** @param resource|null $process */
    private function stopServer($process): void
    {
        if (is_resource($process)) {
            proc_terminate($process);
            proc_close($process);
        }
    }

    /**
     * @param array<string, mixed> $overrides
     * @return array{0:int,1:array<string,mixed>}
     */
    private function postWebhook(string $secret, array $overrides = [], string $path = '/whatsapp/onboarding/webhook'): array
    {
        $payload = array_merge([
            'source' => 'lead_intake_agent',
            'wa_message_id' => 'wamid.test',
            'wa_phone' => '+919999999999',
            'message_text' => 'signup',
            'timestamp' => (string) time(),
            'message_type' => 'text',
            'raw_payload' => [],
        ], $overrides);

        $headers = [];
        if ($secret !== '') {
            $headers[] = 'X-NXTUTORS-INTERNAL-SECRET: ' . $secret;
        }

        return $this->httpPost($this->webhookBaseUrl, $path, $payload, $headers);
    }

    /**
     * @param array<string, mixed> $body
     * @param list<string>         $headers
     * @return array{0:int,1:array<string,mixed>}
     */
    private function httpPost(string $baseUrl, string $path, array $body, array $headers = []): array
    {
        return $this->httpPostRaw($baseUrl, $path, (string) json_encode($body, JSON_UNESCAPED_SLASHES), $headers);
    }

    /**
     * @param list<string> $headers
     * @return array{0:int,1:array<string,mixed>}
     */
    private function httpPostRaw(string $baseUrl, string $path, string $body, array $headers = []): array
    {
        $headerLines = "Content-Type: application/json\r\n";
        foreach ($headers as $header) {
            $headerLines .= $header . "\r\n";
        }

        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => $headerLines,
                'content' => $body,
                'ignore_errors' => true,
                'timeout' => 3,
            ],
        ]);

        $response = @file_get_contents($baseUrl . $path, false, $context);
        $status = $this->statusFromHeaders($http_response_header ?? []);
        $decoded = json_decode((string) $response, true);

        return [$status, is_array($decoded) ? $decoded : []];
    }

    /** @return array{0:int,1:array<string,mixed>} */
    private function httpGet(string $baseUrl, string $path): array
    {
        $context = stream_context_create([
            'http' => ['method' => 'GET', 'ignore_errors' => true, 'timeout' => 3],
        ]);

        $response = @file_get_contents($baseUrl . $path, false, $context);
        $status = $this->statusFromHeaders($http_response_header ?? []);
        $decoded = json_decode((string) $response, true);

        return [$status, is_array($decoded) ? $decoded : []];
    }

    /** @param list<string> $headers */
    private function statusFromHeaders(array $headers): int
    {
        $statusLine = $headers[0] ?? '';
        preg_match('/\s(\d{3})\s/', (string) $statusLine, $matches);

        return isset($matches[1]) ? (int) $matches[1] : 0;
    }
}
