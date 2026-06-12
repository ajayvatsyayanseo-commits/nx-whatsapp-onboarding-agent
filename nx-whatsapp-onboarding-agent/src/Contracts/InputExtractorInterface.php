<?php

declare(strict_types=1);

namespace NxTutors\WhatsAppOnboarding\Contracts;

interface InputExtractorInterface
{
    /** @return array{field?:string,value?:string,confidence:float} */
    public function extract(string $input, string $expectedField): array;
}
