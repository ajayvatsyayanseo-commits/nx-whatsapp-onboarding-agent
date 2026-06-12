<?php

declare(strict_types=1);

namespace NxTutors\WhatsAppOnboarding\Security\AbuseDetection;

use NxTutors\WhatsAppOnboarding\Contracts\MalwareScannerInterface;
use NxTutors\WhatsAppOnboarding\WhatsApp\DTO\InboundWhatsAppMessage;

final readonly class MediaValidationService
{
    private const ALLOWED_MIME_TYPES = [
        'image/jpeg',
        'image/png',
        'image/webp',
        'application/pdf',
    ];

    public function __construct(private MalwareScannerInterface $scanner)
    {
    }

    public function validate(InboundWhatsAppMessage $message): bool
    {
        $mime = (string) ($message->raw['image']['mime_type'] ?? $message->raw['document']['mime_type'] ?? '');
        $mediaId = (string) ($message->raw['image']['id'] ?? $message->raw['document']['id'] ?? $message->text ?? '');

        if ($mediaId === '') {
            return false;
        }

        if ($mime !== '' && ! in_array($mime, self::ALLOWED_MIME_TYPES, true)) {
            return false;
        }

        $size = (int) ($message->raw['image']['file_size'] ?? $message->raw['document']['file_size'] ?? 0);
        $maxBytes = (int) config('whatsapp_onboarding_cost_limits.max_media_download_mb', 10) * 1024 * 1024;
        if ($size > 0 && $size > $maxBytes) {
            return false;
        }

        $this->scanner->assertClean($mediaId);

        return true;
    }
}
