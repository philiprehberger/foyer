<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_memberships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->char('business_id', 26);
            $table->string('role')->default('owner');
            $table->timestamps();

            $table->unique(['user_id', 'business_id']);
            $table->foreign('business_id')->references('id')->on('businesses')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_memberships');
    }
};
