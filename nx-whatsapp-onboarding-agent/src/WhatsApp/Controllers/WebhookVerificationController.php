<?php

declare(strict_types=1);

namespace NxTutors\WhatsAppOnboarding\WhatsApp\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;

final class WebhookVerificationController
{
    public function __invoke(Request $request): Response
    {
        $mode = $request->query('hub_mode', $request->query('hub.mode'));
        $token = $request->query('hub_verify_token', $request->query('hub.verify_token'));
        $challenge = (string) $request->query('hub_challenge', $request->query('hub.challenge', ''));

        if ($mode === 'subscribe' && hash_equals((string) config('whatsapp_onboarding.meta.verify_token'), (string) $token)) {
            return response($challenge, 200);
        }

        return response('Forbidden', 403);
    }
}
