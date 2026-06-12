<?php

declare(strict_types=1);

namespace NxTutors\WhatsAppOnboarding\Contracts;

interface MessageSenderInterface
{
    /** @param array<string, mixed> $metadata */
    public function sendText(string $toPhone, string $body, array $metadata = []): void;

    /** @param array<string, mixed> $parameters */
    public function sendTemplate(string $toPhone, string $templateName, string $language, array $parameters = []): void;

    /**
     * @param list<array{id:string,title:string}> $buttons
     * @param array<string, mixed> $metadata
     */
    public function sendButtons(string $toPhone, string $body, array $buttons, string $fallbackText, array $metadata = []): void;
}
