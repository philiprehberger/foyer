<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('consent_state', function (Blueprint $table) {
            $table->id();
            $table->string('customer_phone_e164');
            $table->string('twilio_number_e164');
            $table->string('state')->default('subscribed');
            $table->timestamp('last_change_at')->useCurrent();

            $table->unique(['customer_phone_e164', 'twilio_number_e164'], 'consent_pair');
        });

        Schema::create('consent_changes', function (Blueprint $table) {
            $table->id();
            $table->string('customer_phone_e164');
            $table->string('twilio_number_e164');
            $table->string('from_state');
            $table->string('to_state');
            $table->char('source_message_id', 26)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['customer_phone_e164', 'twilio_number_e164']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consent_changes');
        Schema::dropIfExists('consent_state');
    }
};
