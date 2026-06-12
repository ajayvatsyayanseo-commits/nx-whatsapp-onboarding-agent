<?php

declare(strict_types=1);

namespace NxTutors\WhatsAppOnboarding\Profile\Exceptions;

use RuntimeException;

final class DuplicateRegisterFieldException extends RuntimeException
{
    public function __construct(public readonly string $field)
    {
        parent::__construct("Duplicate register field: {$field}");
    }
}
