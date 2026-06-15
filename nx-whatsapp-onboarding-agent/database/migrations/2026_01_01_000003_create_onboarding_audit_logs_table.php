<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('onboarding_audit_logs', static function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->foreignId('onboarding_conversation_id')->nullable()->constrained('onboarding_conversations')->nullOnDelete();
            $table->string('action', 120);
            $table->string('actor', 80)->default('system');
            $table->json('masked_metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['onboarding_conversation_id', 'created_at'], 'onboarding_audit_conversation_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('onboarding_audit_logs');
    }
};
