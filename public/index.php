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

if ($path === '/health/live' || $path === '/health/Live') {
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
    json_response(['status' => 'accepted', 'mode' => 'package']);
}

json_response(['status' => 'not_found'], 404);
