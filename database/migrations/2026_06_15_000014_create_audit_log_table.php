<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_log', function (Blueprint $table) {
            $table->id();
            $table->string('actor_type');
            $table->string('actor_id')->nullable();
            $table->char('business_id', 26)->nullable();
            $table->string('event');
            $table->jsonb('payload')->default('{}');
            $table->string('ip')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['business_id', 'event', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_log');
    }
};
