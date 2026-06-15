<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('idempotency_keys', function (Blueprint $table) {
            $table->id();
            $table->string('key');
            $table->string('endpoint');
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->char('business_id', 26)->nullable();
            $table->integer('response_status')->nullable();
            $table->jsonb('response_body')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('expires_at');

            $table->unique(['key', 'endpoint'], 'idem_key');
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('idempotency_keys');
    }
};
