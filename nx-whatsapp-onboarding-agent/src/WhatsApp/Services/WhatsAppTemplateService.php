<?php

declare(strict_types=1);

namespace NxTutors\WhatsAppOnboarding\WhatsApp\Services;

final class WhatsAppTemplateService
{
    public function template(string $key): string
    {
        return (string) config("whatsapp_onboarding.meta.templates.{$key}");
    }

    /** @return array<string, string> */
    public function registry(): array
    {
        return (array) config('whatsapp_onboarding.meta.templates', []);
    }
}
