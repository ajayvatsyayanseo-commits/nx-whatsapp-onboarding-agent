<?php

declare(strict_types=1);

namespace NxTutors\WhatsAppOnboarding\WhatsApp\Services;

use GuzzleHttp\ClientInterface;
use Illuminate\Contracts\Filesystem\Factory as FilesystemFactory;
use NxTutors\WhatsAppOnboarding\Contracts\MediaStorageInterface;
use NxTutors\WhatsAppOnboarding\Contracts\MalwareScannerInterface;
use RuntimeException;

final readonly class WhatsAppMediaService implements MediaStorageInterface
{
    public function __construct(
        private ClientInterface $http,
        private FilesystemFactory $filesystems,
        private MalwareScannerInterface $scanner,
    ) {
    }

    public function storeFromWhatsAppMediaId(string $mediaId, string $purpose): string
    {
        $accessToken = (string) config('whatsapp_onboarding.meta.access_token', '');
        if ($accessToken === '') {
            throw new RuntimeException('Meta WhatsApp access token is not configured.');
        }

        $baseUrl = rtrim((string) config('whatsapp_onboarding.meta.graph_base_url'), '/');
        $version = trim((string) config('whatsapp_onboarding.meta.api_version'), '/');
        $metadata = json_decode((string) $this->http->request('GET', "{$baseUrl}/{$version}/{$mediaId}", [
            'headers' => ['Authorization' => "Bearer {$accessToken}"],
            'timeout' => 5,
        ])->getBody(), true, flags: JSON_THROW_ON_ERROR);

        $mime = (string) ($metadata['mime_type'] ?? '');
        $this->assertAllowed($purpose, $mime, (int) ($metadata['file_size'] ?? 0));

        $content = (string) $this->http->request('GET', (string) $metadata['url'], [
            'headers' => ['Authorization' => "Bearer {$accessToken}"],
            'timeout' => 20,
        ])->getBody();

        $this->scanner->assertClean($mediaId);
        $extension = $this->extensionForMime($mime);
        $filename = 'whatsapp_' . preg_replace('/[^a-z0-9_]+/i', '_', $purpose) . '_' . time() . '_' . bin2hex(random_bytes(6)) . '.' . $extension;
        $key = trim((string) config('whatsapp_onboarding.media.s3_prefix', 'nxtutors/onboarding'), '/')
            . '/' . $purpose . '/' . date('Y/m/d') . '/' . $filename;

        $driver = (string) config('whatsapp_onboarding.media.storage_driver', 'local');
        if ($driver === 'legacy_public_user') {
            $relative = trim((string) config('whatsapp_onboarding.media.local_path', 'storage/user'), '/') . '/' . $filename;
            $absolute = public_path($relative);
            if (! is_dir(dirname($absolute))) {
                mkdir(dirname($absolute), 0755, true);
            }

            file_put_contents($absolute, $content);

            return (string) config('whatsapp_onboarding.media.db_value', 'filename_only') === 'filename_only'
                ? $filename
                : $relative;
        }

        if ($driver === 's3') {
            $disk = $this->filesystems->disk('s3');
            $disk->put($key, $content, ['visibility' => 'private', 'Metadata' => ['scan_status' => 'pending_scan']]);

            return $key;
        }

        $localKey = trim((string) config('whatsapp_onboarding.media.local_path', 'nxtutors/onboarding'), '/') . '/' . $filename;
        $this->filesystems->disk('local')->put($localKey, $content);

        return $localKey;
    }

    private function assertAllowed(string $purpose, string $mime, int $bytes): void
    {
        $allowed = (array) config('whatsapp_onboarding.media.allowed_image_mimes', []);
        if ($purpose === 'degree_certificate' && (bool) config('whatsapp_onboarding.media.degree_allows_pdf', true)) {
            $allowed[] = 'application/pdf';
        }

        if (! in_array($mime, $allowed, true)) {
            throw new RuntimeException('Unsupported WhatsApp media type.');
        }

        $maxBytes = (int) config('whatsapp_onboarding.media.max_kb', 2048) * 1024;
        if ($bytes > $maxBytes) {
            throw new RuntimeException('WhatsApp media exceeds configured maximum size.');
        }
    }

    private function extensionForMime(string $mime): string
    {
        return [
            'image/jpeg' => 'jpg',
            'image/jpg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'application/pdf' => 'pdf',
        ][$mime] ?? 'bin';
    }
}
