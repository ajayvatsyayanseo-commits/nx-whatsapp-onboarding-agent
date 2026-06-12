<?php

declare(strict_types=1);

namespace NxTutors\WhatsAppOnboarding\Observability\Tracing;

final class Tracer
{
    public function traceId(): string
    {
        return request()?->headers->get('X-Trace-Id') ?: bin2hex(random_bytes(16));
    }

    /** @param array<string, mixed> $attributes */
    public function span(string $name, array $attributes = []): void
    {
        // Adapter point for OpenTelemetry SDK in the host Laravel application.
    }
}
