<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('message_deliveries', function (Blueprint $table) {
            $table->id();
            $table->char('message_id', 26);
            $table->string('twilio_sid')->nullable()->unique();
            $table->string('status')->default('queued');
            $table->string('error_code')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('message_id')->references('id')->on('messages')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('message_deliveries');
    }
};
