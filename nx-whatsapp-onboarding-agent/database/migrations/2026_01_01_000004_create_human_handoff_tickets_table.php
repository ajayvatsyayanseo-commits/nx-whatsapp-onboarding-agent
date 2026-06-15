<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('human_handoff_tickets', static function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->foreignId('onboarding_conversation_id')->constrained('onboarding_conversations')->cascadeOnDelete();
            $table->string('wa_phone', 32);
            $table->string('role', 20)->nullable();
            $table->string('reason', 255);
            $table->string('reason_code', 64)->nullable();
            $table->string('status', 32)->default('open');
            $table->string('assigned_to')->nullable();
            $table->timestamp('opened_at')->useCurrent();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'opened_at'], 'human_handoff_status_opened_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('human_handoff_tickets');
    }
};
