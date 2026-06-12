<?php

declare(strict_types=1);

namespace NxTutors\WhatsAppOnboarding\Contracts;

interface PolicyGuardInterface
{
    public function assertSafeConfiguration(): void;

    public function assertCanStart(?string $text): void;

    public function assertRoleEnabled(string $role): void;
}
