<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('onboarding_events', static function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->foreignId('onboarding_conversation_id')->nullable()->constrained('onboarding_conversations')->nullOnDelete();
            $table->string('wa_message_id', 160)->nullable();
            $table->string('wa_phone', 32)->nullable();
            $table->string('idempotency_key', 255)->nullable();
            $table->string('direction', 16);
            $table->string('event_type', 64);
            $table->jsonb('payload')->default('{}');
            $table->string('status', 32)->default('queued');
            $table->timestampTz('webhook_timestamp')->nullable();
            $table->timestampTz('received_at')->nullable();
            $table->timestampTz('processed_at')->nullable();
            $table->timestampsTz();

            $table->unique('wa_message_id', 'onboarding_events_wa_message_id_unique');
            $table->unique('idempotency_key', 'onboarding_events_idempotency_key_unique');
            $table->index(['wa_phone', 'created_at'], 'onboarding_events_phone_created_idx');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('onboarding_events');
    }
};
