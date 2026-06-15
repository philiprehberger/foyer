<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_types', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('business_id', 26);
            $table->string('key');
            $table->string('label');
            $table->text('description')->nullable();
            $table->integer('est_duration_min')->default(60);
            $table->boolean('requires_photos')->default(false);
            $table->timestamps();

            $table->unique(['business_id', 'key']);
            $table->foreign('business_id')->references('id')->on('businesses')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_types');
    }
};
