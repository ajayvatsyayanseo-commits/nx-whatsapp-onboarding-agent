<?php

declare(strict_types=1);

namespace NxTutors\WhatsAppOnboarding\Tests\Feature;

use NxTutors\WhatsAppOnboarding\Contracts\MalwareScannerInterface;
use NxTutors\WhatsAppOnboarding\Security\AbuseDetection\MediaValidationService;
use NxTutors\WhatsAppOnboarding\Tests\TestCase;
use NxTutors\WhatsAppOnboarding\WhatsApp\DTO\InboundWhatsAppMessage;

final class MediaValidationTest extends TestCase
{
    public function testAcceptsAllowedImageWithinSizeLimit(): void
    {
        config()->set('whatsapp_onboarding_cost_limits.max_media_download_mb', 10);
        $service = new MediaValidationService(new class implements MalwareScannerInterface {
            public function assertClean(string $mediaIdOrUrl): void
            {
            }
        });

        $message = new InboundWhatsAppMessage('wamid.test', '+919999999999', null, 'image', [
            'image' => ['id' => 'media-id', 'mime_type' => 'image/jpeg', 'file_size' => 1024],
        ]);

        self::assertTrue($service->validate($message));
    }
}
