<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('phone_numbers', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('business_id', 26);
            $table->string('number_e164')->unique();
            $table->string('messaging_service_sid')->nullable();
            $table->string('campaign_sid')->nullable();
            $table->timestamp('provisioned_at')->nullable();
            $table->timestamp('released_at')->nullable();
            $table->string('status')->default('pending');
            $table->timestamps();

            $table->index(['business_id', 'status']);
            $table->foreign('business_id')->references('id')->on('businesses')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('phone_numbers');
    }
};
