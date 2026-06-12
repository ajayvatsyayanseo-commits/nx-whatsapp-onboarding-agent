<?php

declare(strict_types=1);

namespace NxTutors\WhatsAppOnboarding\Contracts;

interface MediaStorageInterface
{
    public function storeFromWhatsAppMediaId(string $mediaId, string $purpose): string;
}
