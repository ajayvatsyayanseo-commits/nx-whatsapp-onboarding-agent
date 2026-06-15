<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('onboarding_conversations', static function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->string('wa_phone', 32);
            $table->string('role', 20)->nullable();
            $table->string('current_state', 80);
            $table->string('status', 32)->default('open');
            $table->string('locale', 10)->default('en');
            $table->json('context')->nullable();
            $table->json('field_attempts')->nullable();
            $table->unsignedSmallInteger('invalid_attempts')->default(0);
            $table->unsignedInteger('lock_version')->default(0);
            $table->text('terms_url')->nullable();
            $table->string('terms_version', 80)->nullable();
            $table->string('terms_role', 20)->nullable();
            $table->string('terms_accepted_message_id', 160)->nullable();
            $table->json('terms_metadata')->nullable();
            $table->timestamp('terms_accepted_at')->nullable();
            $table->timestamp('otp_verified_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('last_message_at')->nullable();
            $table->timestamps();

            $table->index('wa_phone');
            $table->index('status');
            $table->index('role');
            $table->index('current_state');
            $table->index(['wa_phone', 'status', 'role', 'current_state'], 'onboarding_conversations_lookup_idx');
            $table->index(['wa_phone', 'lock_version'], 'onboarding_conversations_lock_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('onboarding_conversations');
    }
};
