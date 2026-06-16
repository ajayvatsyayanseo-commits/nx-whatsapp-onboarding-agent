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

function requested_signup_role(string $text): string
{
    $text = normalized_text($text);

    if (str_contains($text, 'tutor') || str_contains($text, 'teacher')) {
        return 'tutor';
    }

    if (str_contains($text, 'student')) {
        return 'student';
    }

    return '';
}

function role_selection_message(): string
{
    return 'Welcome to NXtutors signup. Are you joining as a student or tutor?';
}

function student_start_message(): string
{
    return "Great, let's create your student profile. What is your full name?";
}

function tutor_start_message(): string
{
    return "Great, let's create your tutor profile. What is your full name?";
}

function onboarding_reply_text(string $text): string
{
    return match (requested_signup_role($text)) {
        'student' => student_start_message(),
        'tutor' => tutor_start_message(),
        default => role_selection_message(),
    };
}

function flat_or_meta_value(array $payload, string $flatKey, string $metaKey): string
{
    $flatValue = $payload[$flatKey] ?? null;
    if (is_scalar($flatValue) && (string) $flatValue !== '') {
        return (string) $flatValue;
    }

    $message = $payload['entry'][0]['changes'][0]['value']['messages'][0] ?? null;
    if (! is_array($message)) {
        return '';
    }

    return match ($metaKey) {
        'message_id' => (string) ($message['id'] ?? ''),
        'phone' => (string) ($message['from'] ?? ''),
        'text' => (string) (
            $message['text']['body']
            ?? $message['button']['text']
            ?? $message['interactive']['button_reply']['title']
            ?? $message['interactive']['list_reply']['title']
            ?? ''
        ),
        default => '',
    };
}

function mask_phone(string $phone): string
{
    $digits = preg_replace('/\D+/', '', $phone) ?? '';
    if (strlen($digits) <= 4) {
        return $digits === '' ? '' : '****';
    }

    return '+' . substr($digits, 0, 2) . str_repeat('*', max(2, strlen($digits) - 6)) . substr($digits, -4);
}

function masked_request_log(string $event, array $context): void
{
    $safe = [
        'event' => $event,
        'request_id' => request_header('X-Request-Id') ?: bin2hex(random_bytes(8)),
        'wa_message_id' => (string) ($context['wa_message_id'] ?? ''),
        'wa_phone' => mask_phone((string) ($context['wa_phone'] ?? '')),
    ];

    error_log(json_encode($safe, JSON_UNESCAPED_SLASHES));
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

if (($path === '/whatsapp/onboarding/webhook' || $path === '/index.php') && $method === 'POST') {
    $body = file_get_contents('php://input') ?: '{}';
    $payload = json_decode($body, true);
    $payload = is_array($payload) ? $payload : [];

    $providedInternalSecret = request_header('X-NXTUTORS-INTERNAL-SECRET');
    $isInternalHandoff = $providedInternalSecret !== '' || ($payload['source'] ?? '') === 'lead_intake_agent';

    if ($isInternalHandoff) {
        $configuredSecret = getenv('ONBOARDING_AGENT_INTERNAL_SECRET') ?: '';
        if ($configuredSecret === '' || ! hash_equals($configuredSecret, $providedInternalSecret)) {
            json_response(['error' => 'invalid internal secret'], 401);
        }

        $messageText = flat_or_meta_value($payload, 'message_text', 'text');
        if ($messageText === '') {
            $messageText = flat_or_meta_value($payload, 'text', 'text');
        }
        $waPhone = flat_or_meta_value($payload, 'wa_phone', 'phone');
        $messageId = flat_or_meta_value($payload, 'wa_message_id', 'message_id');

        masked_request_log('lead_intake_handoff_received', [
            'wa_phone' => $waPhone,
            'wa_message_id' => $messageId,
        ]);

        if (! is_signup_intent($messageText)) {
            json_response(['status' => 'ignored', 'reason' => 'not_signup_intent'], 202);
        }

        json_response([
            'status' => 'accepted',
            'mode' => 'lead_intake_handoff',
            'reply_text' => onboarding_reply_text($messageText),
        ], 202);
    }

    json_response(['status' => 'accepted', 'mode' => 'package']);
}

json_response(['status' => 'not_found'], 404);
