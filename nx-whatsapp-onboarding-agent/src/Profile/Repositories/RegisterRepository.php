<?php

declare(strict_types=1);

namespace NxTutors\WhatsAppOnboarding\Profile\Repositories;

use Illuminate\Support\Facades\DB;
use NxTutors\WhatsAppOnboarding\Profile\Exceptions\DuplicateRegisterFieldException;
use NxTutors\WhatsAppOnboarding\Profile\Models\Register;

class RegisterRepository
{
    public function findByPhone(string $phone): ?Register
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? $phone;
        $variants = array_values(array_unique(array_filter([
            $phone,
            $digits,
            str_starts_with($digits, '91') ? '+' . $digits : null,
            strlen($digits) === 10 ? '+91' . $digits : null,
            strlen($digits) === 10 ? '91' . $digits : null,
        ])));

        return Register::query()->whereIn('phone', $variants)->first();
    }

    public function findByEmail(string $email): ?Register
    {
        return Register::query()->where('email', $email)->first();
    }

    public function findByDocumentNumber(string $documentNumber): ?Register
    {
        return Register::query()->where('document_number', $documentNumber)->first();
    }

    public function userIdExists(string $userId): bool
    {
        return Register::query()->where('user_id', $userId)->exists();
    }

    /** @param array<string, mixed> $attributes */
    public function createProfile(array $attributes): Register
    {
        return DB::transaction(function () use ($attributes): Register {
            $phone = (string) $attributes['phone'];
            $email = (string) $attributes['email'];
            $documentNumber = (string) ($attributes['document_number'] ?? '');
            $userId = (string) $attributes['user_id'];

            if ($this->findByPhoneForUpdate($phone) !== null) {
                throw new DuplicateRegisterFieldException('phone');
            }

            if ($this->findByEmailForUpdate($email) !== null) {
                throw new DuplicateRegisterFieldException('email');
            }

            if ($documentNumber !== '' && $this->findByDocumentNumberForUpdate($documentNumber) !== null) {
                throw new DuplicateRegisterFieldException('document_number');
            }

            if ($this->findByUserIdForUpdate($userId) !== null) {
                throw new DuplicateRegisterFieldException('user_id');
            }

            return Register::query()->create($attributes);
        }, 3);
    }

    private function findByPhoneForUpdate(string $phone): ?Register
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? $phone;
        $variants = array_values(array_unique(array_filter([
            $phone,
            $digits,
            str_starts_with($digits, '91') ? '+' . $digits : null,
            strlen($digits) === 10 ? '+91' . $digits : null,
            strlen($digits) === 10 ? '91' . $digits : null,
        ])));

        return Register::query()->whereIn('phone', $variants)->lockForUpdate()->first();
    }

    private function findByEmailForUpdate(string $email): ?Register
    {
        return Register::query()->where('email', $email)->lockForUpdate()->first();
    }

    private function findByDocumentNumberForUpdate(string $documentNumber): ?Register
    {
        return Register::query()->where('document_number', $documentNumber)->lockForUpdate()->first();
    }

    private function findByUserIdForUpdate(string $userId): ?Register
    {
        return Register::query()->where('user_id', $userId)->lockForUpdate()->first();
    }
}
