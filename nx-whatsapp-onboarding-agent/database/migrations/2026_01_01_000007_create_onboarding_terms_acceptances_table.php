<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('onboarding_terms_acceptances', static function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->foreignId('onboarding_conversation_id')->constrained('onboarding_conversations')->cascadeOnDelete();
            $table->string('role', 20);
            $table->text('terms_url');
            $table->string('terms_version', 80);
            $table->timestampTz('accepted_at');
            $table->string('acceptance_message_id', 160)->nullable();
            $table->string('acceptance_text_hash', 128);
            $table->string('user_phone_hash', 128);
            $table->string('ip_hash', 128)->nullable();
            $table->string('user_agent_hash', 128)->nullable();
            $table->jsonb('metadata')->default('{}');
            $table->timestampsTz();

            $table->index(['onboarding_conversation_id', 'accepted_at'], 'terms_acceptance_conversation_idx');
            $table->index(['user_phone_hash', 'role'], 'terms_acceptance_phone_role_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('onboarding_terms_acceptances');
    }
};
