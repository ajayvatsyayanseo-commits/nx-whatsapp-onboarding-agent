<?php

declare(strict_types=1);

namespace NxTutors\WhatsAppOnboarding\Security\Guards;

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use NxTutors\WhatsAppOnboarding\Conversation\Services\CommandDetector;

final readonly class InputGuardrailService
{
    public function __construct(
        private CacheRepository $cache,
        private CommandDetector $commands,
    ) {
    }

    public function allowsMessage(?string $text, string $phone, ?string $ip = null): bool
    {
        $text = (string) $text;
        if (mb_strlen($text) > (int) config('whatsapp_onboarding_security.input_guardrails.max_message_length', 2000)) {
            return false;
        }

        if ($this->looksLikeInjection($text)) {
            return false;
        }

        return $this->checkRate('phone', $phone, (int) config('whatsapp_onboarding_security.input_guardrails.rate_limit_per_phone_per_minute', 30))
            && ($ip === null || $this->checkRate('ip', $ip, (int) config('whatsapp_onboarding_security.input_guardrails.rate_limit_per_ip_per_minute', 120)));
    }

    public function isAllowedCommand(?string $text): bool
    {
        $command = $this->commands->detect($text)['command'];

        return $command->value !== 'unknown' || trim((string) $text) !== '';
    }

    private function looksLikeInjection(string $text): bool
    {
        if (! (bool) config('whatsapp_onboarding_security.input_guardrails.block_injection_patterns', true)) {
            return false;
        }

        return preg_match('/(<script|<\/script|union\s+select|drop\s+table|insert\s+into|delete\s+from|--|\/\*)/i', $text) === 1;
    }

    private function checkRate(string $scope, string $value, int $limit): bool
    {
        $key = 'nxtutors:onboarding:rate:' . $scope . ':' . hash('sha256', $value) . ':' . now()->format('YmdHi');
        $count = (int) $this->cache->get($key, 0);
        if ($count >= $limit) {
            return false;
        }

        $this->cache->put($key, $count + 1, now()->addMinutes(2));

        return true;
    }
}
