<?php

declare(strict_types=1);

namespace NxTutors\WhatsAppOnboarding\Profile\Services;

use NxTutors\WhatsAppOnboarding\Profile\Repositories\RegisterRepository;

final readonly class DuplicateProfileGuard
{
    public function __construct(private RegisterRepository $registers)
    {
    }

    /** @param array<string, mixed> $context */
    public function check(array $context): DuplicateCheckResult
    {
        if (($context['phone'] ?? null) && $this->registers->findByPhone((string) $context['phone']) !== null) {
            return DuplicateCheckResult::conflict('phone');
        }

        if (($context['email'] ?? null) && $this->registers->findByEmail((string) $context['email']) !== null) {
            return DuplicateCheckResult::conflict('email');
        }

        if (($context['document_number'] ?? null) && $this->registers->findByDocumentNumber((string) $context['document_number']) !== null) {
            return DuplicateCheckResult::conflict('document_number');
        }

        return DuplicateCheckResult::ok();
    }
}
