<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('register')) {
            return;
        }

        if (! Schema::hasColumn('register', 'force_password_reset')) {
            Schema::table('register', static function (Blueprint $table): void {
                $table->boolean('force_password_reset')->default(false);
            });
        }

        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        if (Schema::hasColumn('register', 'phone')) {
            DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS register_phone_unique_idx ON register (phone) WHERE phone IS NOT NULL AND phone <> \'\'');
        }

        if (Schema::hasColumn('register', 'user_id')) {
            DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS register_user_id_unique_not_null_idx ON register (user_id) WHERE user_id IS NOT NULL AND user_id <> \'\'');
        }

        if (Schema::hasColumn('register', 'email')) {
            DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS register_email_unique_not_null_idx ON register (email) WHERE email IS NOT NULL AND email <> \'\'');
        }

        if (Schema::hasColumn('register', 'document_number')) {
            DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS register_document_number_unique_not_null_idx ON register (document_number) WHERE document_number IS NOT NULL AND document_number <> \'\'');
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP INDEX IF EXISTS register_phone_unique_idx');
            DB::statement('DROP INDEX IF EXISTS register_user_id_unique_not_null_idx');
            DB::statement('DROP INDEX IF EXISTS register_email_unique_not_null_idx');
            DB::statement('DROP INDEX IF EXISTS register_document_number_unique_not_null_idx');
        }

        if (Schema::hasTable('register') && Schema::hasColumn('register', 'force_password_reset')) {
            Schema::table('register', static function (Blueprint $table): void {
                $table->dropColumn('force_password_reset');
            });
        }
    }
};
