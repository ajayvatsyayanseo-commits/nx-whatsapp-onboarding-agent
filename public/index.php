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
    return "Welcome to NXtutors signup. Please choose one:\n1. Student signup\n2. Tutor signup";
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
            json_response(['status' => 'ignored', 'reason' => 'not_signup_intent'], 202);
        }

        json_response([
            'status' => 'ok',
            'mode' => 'lead_intake_handoff',
            'reply_text' => onboarding_reply_text($messageText),
        ]);
    }

    json_response(['status' => 'accepted', 'mode' => 'package']);
}

json_response(['status' => 'not_found'], 404);
