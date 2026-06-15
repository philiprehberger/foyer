<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('out_of_scope_log', function (Blueprint $table) {
            $table->id();
            $table->char('conversation_id', 26);
            $table->char('business_id', 26);
            $table->text('text');
            $table->string('reason');
            $table->string('suggested_service_category')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('business_id')->references('id')->on('businesses')->cascadeOnDelete();
            $table->foreign('conversation_id')->references('id')->on('conversations')->cascadeOnDelete();
            $table->index(['business_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('out_of_scope_log');
    }
};
