<?php

declare(strict_types=1);

namespace NxTutors\WhatsAppOnboarding\Contracts;

interface UserIdGeneratorInterface
{
    public function generate(string $role): string;
}
