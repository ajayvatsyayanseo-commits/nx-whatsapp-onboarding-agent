<?php

declare(strict_types=1);

namespace NxTutors\WhatsAppOnboarding\Profile\Repositories;

use Illuminate\Support\Facades\DB;
use NxTutors\WhatsAppOnboarding\Common\Support\PhoneNormalizer;
use NxTutors\WhatsAppOnboarding\Profile\Exceptions\DuplicateRegisterFieldException;
use NxTutors\WhatsAppOnboarding\Profile\Models\Register;

class RegisterRepository
{
    public function __construct(private readonly PhoneNormalizer $phones)
    {
    }

    public function findByPhone(string $phone): ?Register
    {
        return Register::query()->whereIn('phone', $this->phones->variants($phone))->first();
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

    public function maxNumericUserId(): int
    {
        $driver = DB::connection()->getDriverName();
        if ($driver === 'mysql') {
            return (int) Register::query()
                ->whereNotNull('user_id')
                ->where('user_id', 'regexp', '^[0-9]+$')
                ->max(DB::raw('CAST(user_id AS UNSIGNED)'));
        }

        if ($driver === 'pgsql') {
            return (int) Register::query()
                ->whereNotNull('user_id')
                ->whereRaw("user_id ~ '^[0-9]+$'")
                ->max(DB::raw('CAST(user_id AS BIGINT)'));
        }

        return (int) Register::query()->whereNotNull('user_id')->max('user_id');
    }

    public function acquireUserIdLock(): bool
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return true;
        }

        $lock = (string) config('whatsapp_onboarding.nxtutors_legacy.mysql_user_id_lock_name', 'nxtutors_register_user_id_generation');

        return (int) (DB::selectOne('SELECT GET_LOCK(?, 10) AS acquired', [$lock])->acquired ?? 0) === 1;
    }

    public function releaseUserIdLock(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        $lock = (string) config('whatsapp_onboarding.nxtutors_legacy.mysql_user_id_lock_name', 'nxtutors_register_user_id_generation');
        DB::selectOne('SELECT RELEASE_LOCK(?) AS released', [$lock]);
    }

    /** @param array<string, mixed> $attributes */
    public function createProfile(array $attributes): Register
    {
        return DB::transaction(function () use ($attributes): Register {
            if (! $this->acquireUserIdLock()) {
                throw new DuplicateRegisterFieldException('user_id');
            }

            try {
                if ((string) config('whatsapp_onboarding.nxtutors_legacy.user_id_mode', 'legacy_numeric') === 'legacy_numeric') {
                    $attributes['user_id'] = (string) ($this->maxNumericUserId() + 1);
                }

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
            } finally {
                $this->releaseUserIdLock();
            }
        }, 3);
    }

    private function findByPhoneForUpdate(string $phone): ?Register
    {
        return Register::query()->whereIn('phone', $this->phones->variants($phone))->lockForUpdate()->first();
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
