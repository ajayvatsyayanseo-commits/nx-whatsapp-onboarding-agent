<?php

declare(strict_types=1);

namespace NxTutors\WhatsAppOnboarding\Common\ValueObjects;

final readonly class PhoneNumber
{
    public function __construct(public string $value)
    {
    }

    public function masked(): string
    {
        return strlen($this->value) <= 4 ? '****' : str_repeat('*', strlen($this->value) - 4) . substr($this->value, -4);
    }
}
