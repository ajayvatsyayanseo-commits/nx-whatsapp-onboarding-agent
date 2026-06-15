<?php

declare(strict_types=1);

namespace NxTutors\WhatsAppOnboarding\Profile\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use NxTutors\WhatsAppOnboarding\Profile\Models\Register;

final class RegisterCompatibilityAuditor
{
    /** @return array<string, mixed> */
    public function preflight(): array
    {
        $required = ['user_id', 'name', 'email', 'password', 'phone', 'status', 'otp_status', 'join_as'];
        $registerExists = Schema::hasTable('register');
        $missing = [];
        foreach ($required as $column) {
            if (! $registerExists || ! Schema::hasColumn('register', $column)) {
                $missing[] = $column;
            }
        }

        $mediaPath = public_path((string) config('whatsapp_onboarding.media.local_path', 'storage/user'));

        return [
            'db_driver' => DB::connection()->getDriverName(),
            'register_table_exists' => $registerExists,
            'missing_required_columns' => $missing,
            'login_identifier' => config('whatsapp_onboarding.nxtutors_legacy.login_identifier'),
            'login_url' => config('whatsapp_onboarding.dashboard.login_url'),
            'student_dashboard_url' => config('whatsapp_onboarding.dashboard.student_url'),
            'tutor_dashboard_url' => config('whatsapp_onboarding.dashboard.tutor_url'),
            'terms_student_url' => config('whatsapp_onboarding.terms.student_url'),
            'privacy_student_url' => config('whatsapp_onboarding.terms.student_privacy_url'),
            'media_path' => $mediaPath,
            'media_path_writable' => is_dir($mediaPath) ? is_writable($mediaPath) : is_writable(dirname($mediaPath)),
            'force_password_reset_column_exists' => $registerExists && Schema::hasColumn('register', 'force_password_reset'),
            'onboarding_tables_exist' => $this->onboardingTablesExist(),
            'duplicates' => $registerExists ? $this->duplicateCounts() : [],
        ];
    }

    /** @return array<string, int> */
    public function duplicateCounts(): array
    {
        if (! Schema::hasTable('register')) {
            return ['email' => 0, 'phone' => 0, 'user_id' => 0, 'document_number' => 0];
        }

        return [
            'email' => $this->duplicateCount('email'),
            'phone' => $this->duplicateCount('phone'),
            'user_id' => $this->duplicateCount('user_id'),
            'document_number' => $this->duplicateCount('document_number'),
        ];
    }

    public function hasBlockingPreflightFailure(): bool
    {
        $report = $this->preflight();

        if (! $report['register_table_exists'] || $report['missing_required_columns'] !== []) {
            return true;
        }

        foreach (['login_url', 'student_dashboard_url', 'tutor_dashboard_url', 'terms_student_url', 'privacy_student_url'] as $key) {
            if ((string) ($report[$key] ?? '') === '') {
                return true;
            }
        }

        if ((bool) config('whatsapp_onboarding.profile.create_real_profile', false)) {
            return array_sum($report['duplicates']) > 0;
        }

        return false;
    }

    private function duplicateCount(string $column): int
    {
        if (! Schema::hasColumn('register', $column)) {
            return 0;
        }

        $duplicates = DB::table('register')
            ->select($column)
            ->whereNotNull($column)
            ->where($column, '<>', '')
            ->groupBy($column)
            ->havingRaw('COUNT(*) > 1');

        return (int) DB::query()
            ->fromSub($duplicates, 'duplicate_register_values')
            ->count();
    }

    private function onboardingTablesExist(): bool
    {
        foreach (['onboarding_conversations', 'onboarding_events', 'onboarding_audit_logs', 'onboarding_terms_acceptances', 'human_handoff_tickets', 'onboarding_profile_metadata'] as $table) {
            if (! Schema::hasTable($table)) {
                return false;
            }
        }

        return true;
    }
}
