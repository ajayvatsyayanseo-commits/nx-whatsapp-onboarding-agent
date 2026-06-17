<?php

declare(strict_types=1);

/**
 * NXtutors WhatsApp Onboarding Agent — production HTTP entrypoint.
 *
 * Architecture (shared Meta WhatsApp number):
 *
 *   Meta WhatsApp Cloud API
 *     -> Lead Intake Agent (public Meta webhook owner)
 *     -> Lead Intake detects a signup/onboarding message
 *     -> Lead Intake POSTs an internal handoff here with X-NXTUTORS-INTERNAL-SECRET
 *     -> Onboarding validates the internal secret, computes reply_text, and returns it
 *     -> Lead Intake sends exactly ONE WhatsApp reply
 *
 * This agent is NOT the public Meta webhook for the shared number. It still
 * keeps genuine Meta signature verification for any direct Meta webhook request
 * so the endpoint is safe if it is ever pointed at directly.
 */

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

function env_value(string ...$names): string
{
    foreach ($names as $name) {
        $value = getenv($name);
        if ($value !== false && $value !== '') {
            return (string) $value;
        }
    }

    return '';
}

function app_env(): string
{
    return strtolower(env_value('APP_ENV') ?: 'production');
}

function is_production(): bool
{
    return app_env() === 'production';
}

function correlation_id(): string
{
    $id = request_header('X-Correlation-Id') ?: request_header('X-Request-Id');

    return $id !== '' ? $id : bin2hex(random_bytes(8));
}

function normalized_text(string $text): string
{
    return trim((string) preg_replace('/\s+/', ' ', strtolower($text)));
}

/**
 * Return the first non-empty scalar among the given payload keys.
 *
 * @param array<string, mixed> $payload
 * @param list<string>         $keys
 */
function first_scalar(array $payload, array $keys): string
{
    foreach ($keys as $key) {
        $value = $payload[$key] ?? null;
        if (is_scalar($value) && (string) $value !== '') {
            return (string) $value;
        }
    }

    return '';
}

/** @param array<string, mixed> $payload */
function nested_message(array $payload): ?array
{
    $message = $payload['entry'][0]['changes'][0]['value']['messages'][0] ?? null;

    return is_array($message) ? $message : null;
}

/**
 * Normalize the inbound phone across lead-intake aliases and Meta payloads.
 *
 * Aliases: wa_phone | phone | from
 *
 * @param array<string, mixed> $payload
 */
function normalize_phone(array $payload): string
{
    $value = first_scalar($payload, ['wa_phone', 'phone', 'from']);
    if ($value !== '') {
        return $value;
    }

    $message = nested_message($payload);

    return $message !== null ? (string) ($message['from'] ?? '') : '';
}

/**
 * Normalize the inbound message text across aliases and Meta payloads.
 *
 * Aliases: message_text | text | body
 *
 * @param array<string, mixed> $payload
 */
function normalize_text(array $payload): string
{
    $value = first_scalar($payload, ['message_text', 'text', 'body']);
    if ($value !== '') {
        return $value;
    }

    $message = nested_message($payload);
    if ($message === null) {
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

/**
 * Normalize the WhatsApp message id across aliases and Meta payloads.
 *
 * Aliases: wa_message_id | message_id | id
 *
 * @param array<string, mixed> $payload
 */
function normalize_message_id(array $payload): string
{
    $value = first_scalar($payload, ['wa_message_id', 'message_id', 'id']);
    if ($value !== '') {
        return $value;
    }

    $message = nested_message($payload);

    return $message !== null ? (string) ($message['id'] ?? '') : '';
}

/**
 * Detect the onboarding role the user is asking for.
 *
 * Detection categories: student, parent/student, tutor/teacher, unknown.
 * The returned role is normalized to the response contract: student|tutor|unknown.
 */
function detect_role(string $text): string
{
    $text = normalized_text($text);

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

function role_selection_message(): string
{
    return "👋 Welcome to NXtutors signup. Are you joining as a:\n1. Student\n2. Tutor\n\nReply 1 or 2 (or type \"student\" / \"tutor\").";
}

function student_start_message(): string
{
    return "Great, let's create your student profile. What is your full name?";
}

function tutor_start_message(): string
{
    return "Great, let's create your tutor profile. What is your full name?";
}

function onboarding_reply_text(string $role): string
{
    return match ($role) {
        'student' => student_start_message(),
        'tutor' => tutor_start_message(),
        default => role_selection_message(),
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

/**
 * Persistent, race-safe idempotency claim for a wa_message_id.
 *
 * Returns true when the id is claimed for the first time (process it), or false
 * when it was already processed (duplicate). A filesystem store gives per-task
 * idempotency for retry storms; for cross-task dedup point
 * ONBOARDING_IDEMPOTENCY_DIR at a shared volume or run the Laravel package
 * (cache/DB backed) form. An empty id cannot be deduplicated, so it is allowed.
 */
function handoff_claim(string $messageId, int $ttlSeconds = 86400): bool
{
    if ($messageId === '') {
        return true;
    }

    $dir = env_value('ONBOARDING_IDEMPOTENCY_DIR') ?: (sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'nxtutors_onboarding_idemp');
    if (! is_dir($dir)) {
        @mkdir($dir, 0700, true);
    }

    $file = $dir . DIRECTORY_SEPARATOR . sha1($messageId) . '.json';

    if (is_file($file) && (time() - (int) @filemtime($file)) > $ttlSeconds) {
        @unlink($file);
    }

    $handle = @fopen($file, 'x');
    if ($handle === false) {
        return false; // already claimed → duplicate
    }

    fwrite($handle, json_encode(['wa_message_id' => $messageId, 'claimed_at' => time()], JSON_UNESCAPED_SLASHES));
    fclose($handle);

    return true;
}

/** @param array<string, mixed> $context */
function handoff_log(array $context): void
{
    error_log(json_encode(array_merge(['event' => 'lead_intake_handoff'], $context), JSON_UNESCAPED_SLASHES));
}

function verify_meta_signature(string $rawBody, string $signatureHeader): bool
{
    $secret = env_value('META_WHATSAPP_APP_SECRET', 'META_APP_SECRET');

    if ($secret === '') {
        // No app secret configured: only tolerate this outside production so a
        // direct Meta webhook is never accepted unverified in prod.
        return ! is_production();
    }

    if ($signatureHeader === '' || ! str_starts_with($signatureHeader, 'sha256=')) {
        return false;
    }

    $expected = 'sha256=' . hash_hmac('sha256', $rawBody, $secret);

    return hash_equals($expected, $signatureHeader);
}

/** @return array<string, mixed> */
function check_db(): array
{
    $host = env_value('DB_HOST');
    $connection = env_value('DB_CONNECTION');
    if ($host === '' && $connection === '') {
        return ['configured' => false, 'ok' => null];
    }

    if (! class_exists('PDO')) {
        return ['configured' => true, 'ok' => false, 'error' => 'pdo_unavailable'];
    }

    $driver = $connection === 'pgsql' ? 'pgsql' : 'mysql';
    $port = env_value('DB_PORT') ?: ($driver === 'pgsql' ? '5432' : '3306');
    $database = env_value('DB_DATABASE', 'DB_NAME');

    try {
        $pdo = new PDO(
            sprintf('%s:host=%s;port=%s;dbname=%s', $driver, $host, $port, $database),
            env_value('DB_USERNAME', 'DB_USER'),
            env_value('DB_PASSWORD'),
            [PDO::ATTR_TIMEOUT => 2, PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
        );
        $pdo->query('SELECT 1');

        return ['configured' => true, 'ok' => true];
    } catch (\Throwable $e) {
        return ['configured' => true, 'ok' => false];
    }
}

/** @return array<string, mixed> */
function check_whatsapp(): array
{
    $appSecret = env_value('META_WHATSAPP_APP_SECRET', 'META_APP_SECRET') !== '';
    $accessToken = env_value('META_WHATSAPP_ACCESS_TOKEN', 'META_ACCESS_TOKEN') !== '';
    $phoneNumberId = env_value('META_WHATSAPP_PHONE_NUMBER_ID', 'META_PHONE_NUMBER_ID') !== '';
    $verifyToken = env_value('META_WHATSAPP_VERIFY_TOKEN', 'WHATSAPP_VERIFY_TOKEN') !== '';

    return [
        'app_secret_configured' => $appSecret,
        'access_token_configured' => $accessToken,
        'phone_number_id_configured' => $phoneNumberId,
        'verify_token_configured' => $verifyToken,
        // Direct sending is optional for this agent; lead-intake sends replies.
        'direct_send_ready' => $accessToken && $phoneNumberId,
        'ok' => $appSecret || ($accessToken && $phoneNumberId),
    ];
}

/** @return array<string, mixed> */
function check_internal_handoff(): array
{
    $secretConfigured = env_value('ONBOARDING_AGENT_INTERNAL_SECRET') !== '';
    $routeEnabled = strtolower(env_value('ONBOARDING_HANDOFF_ENABLED') ?: 'true') !== 'false';

    return [
        'onboarding_agent_internal_secret_configured' => $secretConfigured,
        'handoff_route_enabled' => $routeEnabled,
        'ok' => $secretConfigured && $routeEnabled,
    ];
}

/* -------------------------------------------------------------------------- */
/* Health endpoints                                                           */
/* -------------------------------------------------------------------------- */

if ($path === '/' || $path === '/health/live' || $path === '/health/Live') {
    json_response(['status' => 'ok', 'service' => 'nxtutors-whatsapp-onboarding']);
}

if ($path === '/health') {
    $handoff = check_internal_handoff();
    json_response([
        'status' => 'ok',
        'service' => 'nxtutors-whatsapp-onboarding',
        'app_env' => app_env(),
        'checks' => [
            'internal_handoff' => $handoff['ok'],
            'whatsapp' => check_whatsapp()['ok'],
        ],
    ]);
}

if ($path === '/health/db') {
    $db = check_db();
    $status = ($db['configured'] === true && $db['ok'] === false) ? 503 : 200;
    json_response(['status' => $status === 200 ? 'ok' : 'error', 'db' => $db], $status);
}

if ($path === '/health/whatsapp') {
    json_response(['status' => 'ok', 'whatsapp' => check_whatsapp()]);
}

if ($path === '/health/internal-handoff') {
    $handoff = check_internal_handoff();
    // Surface a clear signal in production when the secret is missing.
    $status = (is_production() && ! $handoff['onboarding_agent_internal_secret_configured']) ? 503 : 200;
    json_response([
        'status' => $status === 200 ? 'ok' : 'misconfigured',
        'internal_handoff' => $handoff,
    ], $status);
}

if ($path === '/health/ready' || $path === '/api/nx-whatsapp-onboarding/health') {
    json_response(['status' => 'ready', 'service' => 'nxtutors-whatsapp-onboarding', 'mode' => 'package']);
}

/* -------------------------------------------------------------------------- */
/* Meta webhook verification (GET)                                            */
/* -------------------------------------------------------------------------- */

if ($path === '/whatsapp/onboarding/webhook' && $method === 'GET') {
    $verifyToken = env_value('META_WHATSAPP_VERIFY_TOKEN', 'WHATSAPP_VERIFY_TOKEN');
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

/* -------------------------------------------------------------------------- */
/* Webhook POST: internal handoff OR genuine Meta webhook                      */
/* -------------------------------------------------------------------------- */

if (($path === '/whatsapp/onboarding/webhook' || $path === '/index.php') && $method === 'POST') {
    $maxBytes = (int) (env_value('WHATSAPP_ONBOARDING_MAX_WEBHOOK_BYTES') ?: 262144);
    $rawBody = file_get_contents('php://input');
    $rawBody = $rawBody === false ? '' : $rawBody;
    if (strlen($rawBody) > $maxBytes) {
        json_response(['status' => 'error', 'reason' => 'payload_too_large'], 413);
    }

    $payload = json_decode($rawBody !== '' ? $rawBody : '{}', true);
    $payload = is_array($payload) ? $payload : [];

    $providedSecret = request_header('X-NXTUTORS-INTERNAL-SECRET');
    $source = is_scalar($payload['source'] ?? null) ? (string) $payload['source'] : '';
    $isInternalHandoff = $providedSecret !== '' || $source === 'lead_intake_agent';

    if ($isInternalHandoff) {
        $correlationId = correlation_id();
        $configuredSecret = env_value('ONBOARDING_AGENT_INTERNAL_SECRET');

        // (Req 3) Server-side secret missing. Never silently accept a handoff.
        if ($configuredSecret === '') {
            handoff_log([
                'correlation_id' => $correlationId,
                'mode' => 'lead_intake_handoff',
                'internal_secret_valid' => false,
                'reason' => 'server_secret_not_configured',
            ]);

            if (is_production()) {
                json_response([
                    'status' => 'error',
                    'reason' => 'server_internal_secret_not_configured',
                ], 503);
            }

            json_response(['status' => 'unauthorized', 'reason' => 'invalid_internal_secret'], 401);
        }

        // (Req 4) Wrong or missing client secret.
        if ($providedSecret === '' || ! hash_equals($configuredSecret, $providedSecret)) {
            handoff_log([
                'correlation_id' => $correlationId,
                'mode' => 'lead_intake_handoff',
                'internal_secret_valid' => false,
            ]);

            json_response(['status' => 'unauthorized', 'reason' => 'invalid_internal_secret'], 401);
        }

        $messageText = normalize_text($payload);
        $waPhone = normalize_phone($payload);
        $waMessageId = normalize_message_id($payload);
        $detectedRole = detect_role($messageText);

        // (Req 10) Idempotency. A duplicate wa_message_id must not restart the
        // onboarding flow or cause lead-intake to send a duplicate reply.
        if (! handoff_claim($waMessageId)) {
            handoff_log([
                'correlation_id' => $correlationId,
                'wa_message_id' => $waMessageId,
                'wa_phone' => mask_phone($waPhone),
                'source' => $source !== '' ? $source : 'lead_intake_agent',
                'mode' => 'lead_intake_handoff',
                'internal_secret_valid' => true,
                'detected_role' => $detectedRole,
                'reply_text_present' => false,
                'duplicate' => true,
            ]);

            json_response([
                'status' => 'duplicate',
                'mode' => 'lead_intake_handoff',
                'wa_message_id' => $waMessageId,
                'reply_text' => null,
            ]);
        }

        $replyText = onboarding_reply_text($detectedRole);

        // (Req 11) One structured log line per handoff with all required fields.
        handoff_log([
            'correlation_id' => $correlationId,
            'wa_message_id' => $waMessageId,
            'wa_phone' => mask_phone($waPhone),
            'source' => $source !== '' ? $source : 'lead_intake_agent',
            'mode' => 'lead_intake_handoff',
            'internal_secret_valid' => true,
            'detected_role' => $detectedRole,
            'reply_text_present' => $replyText !== '',
            'duplicate' => false,
        ]);

        // (Req 8) Do NOT send WhatsApp here. Return reply_text to lead-intake,
        // which sends exactly one reply to the user.
        json_response([
            'status' => 'accepted',
            'mode' => 'lead_intake_handoff',
            'wa_message_id' => $waMessageId,
            'wa_phone' => $waPhone,
            'detected_role' => $detectedRole,
            'reply_text' => $replyText,
        ]);
    }

    // (Req 9) Not an internal handoff → treat as a genuine Meta webhook and
    // require a valid X-Hub-Signature-256 before doing anything with it.
    $signature = request_header('X-Hub-Signature-256');
    if (! verify_meta_signature($rawBody, $signature)) {
        json_response(['status' => 'forbidden', 'reason' => 'invalid_meta_signature'], 403);
    }

    json_response(['status' => 'received', 'mode' => 'meta_webhook']);
}

json_response(['status' => 'not_found'], 404);
