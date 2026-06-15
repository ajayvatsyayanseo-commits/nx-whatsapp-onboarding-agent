<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('onboarding_profile_metadata', static function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->foreignId('onboarding_conversation_id')->nullable()->constrained('onboarding_conversations')->nullOnDelete();
            $table->string('register_user_id', 80)->nullable();
            $table->string('register_phone_hash', 128);
            $table->string('role', 20);
            $table->boolean('force_password_reset')->default(true);
            $table->boolean('dry_run')->default(false);
            $table->json('metadata')->nullable();
            $table->timestamp('sensitive_purged_at')->nullable();
            $table->timestamps();

            $table->index('register_user_id');
            $table->index(['register_phone_hash', 'role'], 'onboarding_profile_metadata_phone_role_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('onboarding_profile_metadata');
    }
};
