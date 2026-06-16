<?php

declare(strict_types=1);

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

function json_response(array $payload, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($payload, JSON_UNESCAPED_SLASHES);
    exit;
}

function request_header(string $name): string
{
    $serverName = 'HTTP_' . strtoupper(str_replace('-', '_', $name));

    return (string) ($_SERVER[$serverName] ?? '');
}

function normalized_text(string $text): string
{
    return trim((string) preg_replace('/\s+/', ' ', strtolower($text)));
}

function is_signup_intent(string $text): bool
{
    $text = normalized_text($text);
    $keywords = [
        'signup',
        'sign up',
        'register',
        'registration',
        'student signup',
        'student register',
        'tutor signup',
        'tutor register',
        'teacher signup',
        'teacher register',
        'join as student',
        'join as tutor',
        'join as teacher',
        'create account',
        'open account',
        'i want to signup',
        'i want to register',
        'hey nxtutors i want to signup',
    ];

    foreach ($keywords as $keyword) {
        if ($text === $keyword || str_contains($text, $keyword)) {
            return true;
        }
    }

    return false;
}

function role_selection_message(): string
{
    return "Welcome to NXtutors. Please choose signup type:\n1. As a Student\n2. As a Tutor";
}

function send_whatsapp_text(string $phone, string $message): array
{
    $token = getenv('META_WHATSAPP_ACCESS_TOKEN') ?: '';
    $phoneNumberId = getenv('META_WHATSAPP_PHONE_NUMBER_ID') ?: '';
    $apiVersion = getenv('META_WHATSAPP_API_VERSION') ?: 'v20.0';
    $graphBaseUrl = rtrim(getenv('META_GRAPH_BASE_URL') ?: 'https://graph.facebook.com', '/');
    $recipient = preg_replace('/\D+/', '', $phone) ?: '';

    if ($token === '' || $phoneNumberId === '' || $recipient === '') {
        return ['sent' => false, 'reason' => 'missing_meta_config_or_phone'];
    }

    $payload = json_encode([
        'messaging_product' => 'whatsapp',
        'to' => $recipient,
        'type' => 'text',
        'text' => [
            'preview_url' => false,
            'body' => $message,
        ],
    ], JSON_UNESCAPED_SLASHES);

    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => [
                'Authorization: Bearer ' . $token,
                'Content-Type: application/json',
            ],
            'content' => $payload,
            'ignore_errors' => true,
            'timeout' => 8,
        ],
    ]);

    $url = sprintf('%s/%s/%s/messages', $graphBaseUrl, $apiVersion, rawurlencode($phoneNumberId));
    $response = @file_get_contents($url, false, $context);
    $statusLine = $http_response_header[0] ?? '';
    preg_match('/\s(\d{3})\s/', $statusLine, $matches);
    $status = isset($matches[1]) ? (int) $matches[1] : 0;

    return [
        'sent' => $status >= 200 && $status < 300,
        'provider_status' => $status,
        'provider_response' => $status >= 200 && $status < 300 ? 'accepted' : substr((string) $response, 0, 300),
    ];
}

if ($path === '/' || $path === '/health/live' || $path === '/health/Live') {
    json_response(['status' => 'ok', 'service' => 'nxtutors-whatsapp-onboarding']);
}

if ($path === '/health/ready' || $path === '/api/nx-whatsapp-onboarding/health') {
    json_response(['status' => 'ready', 'service' => 'nxtutors-whatsapp-onboarding', 'mode' => 'package']);
}

if ($path === '/whatsapp/onboarding/webhook' && $method === 'GET') {
    $verifyToken = getenv('META_WHATSAPP_VERIFY_TOKEN') ?: getenv('WHATSAPP_VERIFY_TOKEN') ?: '';
    $mode = $_GET['hub_mode'] ?? $_GET['hub.mode'] ?? '';
    $token = $_GET['hub_verify_token'] ?? $_GET['hub.verify_token'] ?? '';
    $challenge = $_GET['hub_challenge'] ?? $_GET['hub.challenge'] ?? '';

    if ($mode === 'subscribe' && $verifyToken !== '' && hash_equals($verifyToken, (string) $token)) {
        http_response_code(200);
        header('Content-Type: text/plain');
        echo (string) $challenge;
        exit;
    }

    json_response(['error' => 'webhook verification failed'], 403);
}

if ($path === '/whatsapp/onboarding/webhook' && $method === 'POST') {
    $body = file_get_contents('php://input') ?: '{}';
    $payload = json_decode($body, true);
    $payload = is_array($payload) ? $payload : [];

    if (($payload['source'] ?? '') === 'lead_intake_agent') {
        $configuredSecret = getenv('ONBOARDING_AGENT_INTERNAL_SECRET') ?: '';
        $providedSecret = request_header('X-NXTUTORS-INTERNAL-SECRET');

        if ($configuredSecret === '' || ! hash_equals($configuredSecret, $providedSecret)) {
            json_response(['error' => 'invalid internal secret'], 401);
        }

        $messageText = (string) ($payload['message_text'] ?? $payload['text'] ?? '');
        if (! is_signup_intent($messageText)) {
            json_response(['status' => 'ignored', 'reason' => 'not_signup_intent']);
        }

        $reply = role_selection_message();
        $sendResult = send_whatsapp_text((string) ($payload['wa_phone'] ?? $payload['phone'] ?? ''), $reply);

        json_response([
            'status' => 'accepted',
            'mode' => 'lead_intake_handoff',
            'reply_text' => $reply,
            'send_result' => $sendResult,
        ], 202);
    }

    json_response(['status' => 'accepted', 'mode' => 'package']);
}

json_response(['status' => 'not_found'], 404);
