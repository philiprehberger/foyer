<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversations', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('business_id', 26);
            $table->string('customer_phone_e164')->nullable();
            $table->string('web_session_id')->nullable();
            $table->string('channel');
            $table->jsonb('state')->default('{}');
            $table->timestamp('phone_verified_at')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('last_message_at')->nullable();
            $table->timestamp('abandoned_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->foreign('business_id')->references('id')->on('businesses')->cascadeOnDelete();
            $table->index(['business_id', 'customer_phone_e164']);
        });

        // Partial index for cross-channel resume lookup
        DB::statement(
            'CREATE INDEX conversations_resume ON conversations '
            .'(business_id, customer_phone_e164, started_at DESC) '
            .'WHERE phone_verified_at IS NOT NULL'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('conversations');
    }
};
