<?php

declare(strict_types=1);

namespace NxTutors\WhatsAppOnboarding\Tests\Feature;

use NxTutors\WhatsAppOnboarding\Tests\TestCase;

final class LeadIntakeWebhookContractTest extends TestCase
{
    /** @var resource|null */
    private $serverProcess = null;

    private string $baseUrl;

    protected function setUp(): void
    {
        parent::setUp();

        $port = random_int(20000, 45000);
        $this->baseUrl = "http://127.0.0.1:{$port}";
        $publicPath = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'public';
        $secret = 'test-internal-secret';

        $descriptorSpec = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $env = array_merge($_ENV, $_SERVER, [
            'ONBOARDING_AGENT_INTERNAL_SECRET' => $secret,
        ]);

        $this->serverProcess = proc_open(
            sprintf('php -S 127.0.0.1:%d -t %s', $port, escapeshellarg($publicPath)),
            $descriptorSpec,
            $pipes,
            dirname(__DIR__, 3),
            $env
        );

        $deadline = microtime(true) + 2.0;
        do {
            $connection = @fsockopen('127.0.0.1', $port);
            if (is_resource($connection)) {
                fclose($connection);
                return;
            }
            usleep(25_000);
        } while (microtime(true) < $deadline);

        self::fail('Webhook test server did not start in time.');
    }

    protected function tearDown(): void
    {
        if (is_resource($this->serverProcess)) {
            proc_terminate($this->serverProcess);
            proc_close($this->serverProcess);
        }

        parent::tearDown();
    }

    public function testValidSecretReturnsReplyTextQuickly(): void
    {
        $startedAt = microtime(true);
        [$status, $body] = $this->postWebhook('test-internal-secret', [
            'message_text' => 'signup',
        ]);

        self::assertLessThan(2.0, microtime(true) - $startedAt);
        self::assertContains($status, [200, 202]);
        self::assertSame('ok', $body['status']);
        self::assertSame(
            "Welcome to NXtutors signup. Please choose one:\n1. Student signup\n2. Tutor signup",
            $body['reply_text']
        );
    }

    public function testInvalidSecretReturnsUnauthorized(): void
    {
        [$status, $body] = $this->postWebhook('wrong-secret', [
            'message_text' => 'signup',
        ]);

        self::assertContains($status, [401, 403]);
        self::assertArrayNotHasKey('reply_text', $body);
    }

    public function testMissingSecretReturnsUnauthorized(): void
    {
        [$status, $body] = $this->postWebhook('', [
            'message_text' => 'signup',
        ]);

        self::assertContains($status, [401, 403]);
        self::assertArrayNotHasKey('reply_text', $body);
    }

    public function testStudentAndTutorSignupStartTheirRoleFlows(): void
    {
        [, $studentBody] = $this->postWebhook('test-internal-secret', [
            'message_text' => 'student signup',
        ]);
        [, $tutorBody] = $this->postWebhook('test-internal-secret', [
            'message_text' => 'tutor signup',
        ]);

        self::assertSame("Great, let's create your student profile. What is your full name?", $studentBody['reply_text']);
        self::assertSame("Great, let's create your tutor profile. What is your full name?", $tutorBody['reply_text']);
    }

    /**
     * @param array<string, mixed> $overrides
     * @return array{0:int,1:array<string,mixed>}
     */
    private function postWebhook(string $secret, array $overrides = []): array
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

        $headers = "Content-Type: application/json\r\n";
        if ($secret !== '') {
            $headers .= "X-NXTUTORS-INTERNAL-SECRET: {$secret}\r\n";
        }

        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => $headers,
                'content' => json_encode($payload, JSON_UNESCAPED_SLASHES),
                'ignore_errors' => true,
                'timeout' => 2,
            ],
        ]);

        $response = file_get_contents($this->baseUrl . '/whatsapp/onboarding/webhook', false, $context);
        $statusLine = $http_response_header[0] ?? '';
        preg_match('/\s(\d{3})\s/', $statusLine, $matches);
        $status = isset($matches[1]) ? (int) $matches[1] : 0;

        $body = json_decode((string) $response, true);

        return [$status, is_array($body) ? $body : []];
    }
}
