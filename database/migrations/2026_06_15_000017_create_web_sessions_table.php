<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('web_sessions', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('business_id', 26);
            $table->string('token_hash');
            $table->string('ip')->nullable();
            $table->string('customer_phone_e164')->nullable();
            $table->string('otp_hash')->nullable();
            $table->timestamp('otp_sent_at')->nullable();
            $table->integer('otp_attempts')->default(0);
            $table->timestamp('phone_verified_at')->nullable();
            $table->timestamp('expires_at');
            $table->timestamps();

            $table->foreign('business_id')->references('id')->on('businesses')->cascadeOnDelete();
            $table->index('token_hash');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('web_sessions');
    }
};
