<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('messages', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('conversation_id', 26);
            // Twilio MessageSid (SMS) or widget UUIDv7 (web). Unique = idempotency.
            $table->string('external_id')->unique();
            $table->string('role');
            $table->text('text')->nullable();
            $table->jsonb('attachments')->default('[]');
            $table->string('phase')->nullable();
            $table->jsonb('intent')->default('{}');
            $table->string('model')->nullable();
            $table->integer('tokens_in')->default(0);
            $table->integer('tokens_out')->default(0);
            $table->bigInteger('cost_micros')->default(0);
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('conversation_id')->references('id')->on('conversations')->cascadeOnDelete();
            $table->index(['conversation_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};
