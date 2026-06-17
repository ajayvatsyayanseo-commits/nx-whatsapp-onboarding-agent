<?php

declare(strict_types=1);

namespace NxTutors\WhatsAppOnboarding\Tests\Feature;

use Illuminate\Support\Facades\Cache;
use NxTutors\WhatsAppOnboarding\Contracts\PolicyGuardInterface;
use NxTutors\WhatsAppOnboarding\Tests\TestCase;

/**
 * Feature tests for the Laravel package form of the webhook controller
 * (NxTutors\WhatsAppOnboarding\WhatsApp\Controllers\WebhookEventController),
 * used when this agent is mounted inside the host Laravel application.
 */
final class LeadIntakeHandoffControllerTest extends TestCase
{
    private const SECRET = 'controller-internal-secret';

    protected function defineEnvironment($app): void
    {
        $app['config']->set('whatsapp_onboarding.internal_handoff.secret', self::SECRET);
        $app['config']->set('whatsapp_onboarding.internal_handoff.enabled', true);
        $app['config']->set('whatsapp_onboarding.meta.app_secret', 'controller-meta-app-secret');
        $app['config']->set('cache.default', 'array');

        // Avoid resolving the full policy-guard dependency chain; the handoff
        // path does not use it, but the controller constructor requires it.
        $app->bind(PolicyGuardInterface::class, static fn () => new class implements PolicyGuardInterface {
            public function assertSafeConfiguration(): void
            {
            }

            public function assertCanStart(?string $text): void
            {
            }

            public function assertRoleEnabled(string $role): void
            {
            }
        });
    }

    public function testValidHandoffIsAccepted(): void
    {
        $response = $this->postJson('/whatsapp/onboarding/webhook', [
            'source' => 'lead_intake_agent',
            'wa_message_id' => 'wamid.ctrl-1',
            'wa_phone' => '919876543210',
            'message_text' => 'I want to register as tutor',
        ], ['X-NXTUTORS-INTERNAL-SECRET' => self::SECRET]);

        $response->assertOk();
        $response->assertJson([
            'status' => 'accepted',
            'mode' => 'lead_intake_handoff',
            'wa_message_id' => 'wamid.ctrl-1',
            'detected_role' => 'tutor',
            'reply_text' => "Great, let's create your tutor profile. What is your full name?",
        ]);
    }

    public function testNumberedMenuReplySelectsRole(): void
    {
        $student = $this->postJson('/whatsapp/onboarding/webhook', [
            'source' => 'lead_intake_agent',
            'wa_message_id' => 'wamid.ctrl-menu-1',
            'message_text' => '1',
        ], ['X-NXTUTORS-INTERNAL-SECRET' => self::SECRET]);

        $student->assertOk();
        $student->assertJson([
            'detected_role' => 'student',
            'reply_text' => "Great, let's create your student profile. What is your full name?",
        ]);

        $tutor = $this->postJson('/whatsapp/onboarding/webhook', [
            'source' => 'lead_intake_agent',
            'wa_message_id' => 'wamid.ctrl-menu-2',
            'message_text' => '2',
        ], ['X-NXTUTORS-INTERNAL-SECRET' => self::SECRET]);

        $tutor->assertOk();
        $tutor->assertJsonPath('detected_role', 'tutor');
    }

    public function testInvalidSecretReturns401(): void
    {
        $response = $this->postJson('/whatsapp/onboarding/webhook', [
            'source' => 'lead_intake_agent',
            'wa_message_id' => 'wamid.ctrl-bad',
            'message_text' => 'signup',
        ], ['X-NXTUTORS-INTERNAL-SECRET' => 'nope']);

        $response->assertStatus(401);
        $response->assertExactJson([
            'status' => 'unauthorized',
            'reason' => 'invalid_internal_secret',
        ]);
    }

    public function testSourceFieldTriggersHandoffWithoutHeader(): void
    {
        // No header at all, but source=lead_intake_agent must still route to the
        // handoff path → 401 because no valid secret was provided.
        $response = $this->postJson('/whatsapp/onboarding/webhook', [
            'source' => 'lead_intake_agent',
            'wa_message_id' => 'wamid.ctrl-source',
            'message_text' => 'student signup',
        ]);

        $response->assertStatus(401);
        $response->assertJsonPath('reason', 'invalid_internal_secret');
    }

    public function testDuplicateMessageIdReturnsDuplicate(): void
    {
        Cache::flush();

        $payload = [
            'source' => 'lead_intake_agent',
            'wa_message_id' => 'wamid.ctrl-dup',
            'message_text' => 'student signup',
        ];
        $headers = ['X-NXTUTORS-INTERNAL-SECRET' => self::SECRET];

        $first = $this->postJson('/whatsapp/onboarding/webhook', $payload, $headers);
        $first->assertOk();
        $first->assertJsonPath('status', 'accepted');

        $second = $this->postJson('/whatsapp/onboarding/webhook', $payload, $headers);
        $second->assertOk();
        $second->assertJson([
            'status' => 'duplicate',
            'mode' => 'lead_intake_handoff',
            'reply_text' => null,
        ]);
    }

    public function testNormalMetaWebhookStillRequiresSignature(): void
    {
        // No handoff markers and no valid Meta signature → 403.
        $response = $this->postJson('/whatsapp/onboarding/webhook', [
            'object' => 'whatsapp_business_account',
            'entry' => [],
        ], ['X-Hub-Signature-256' => 'sha256=deadbeef']);

        $response->assertStatus(403);
        $response->assertJsonPath('reason', 'invalid_meta_signature');
    }

    public function testMissingServerSecretInProductionReturns503(): void
    {
        config()->set('whatsapp_onboarding.internal_handoff.secret', '');
        $this->app['env'] = 'production';

        $response = $this->postJson('/whatsapp/onboarding/webhook', [
            'source' => 'lead_intake_agent',
            'wa_message_id' => 'wamid.ctrl-prod',
            'message_text' => 'signup',
        ], ['X-NXTUTORS-INTERNAL-SECRET' => 'anything']);

        $response->assertStatus(503);
        $response->assertJsonPath('reason', 'server_internal_secret_not_configured');
    }
}
