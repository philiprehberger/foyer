<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('llm_cost_daily', function (Blueprint $table) {
            $table->id();
            $table->char('business_id', 26);
            $table->date('date');
            $table->bigInteger('tokens_in')->default(0);
            $table->bigInteger('tokens_out')->default(0);
            $table->bigInteger('cost_micros')->default(0);
            $table->integer('parse_failures')->default(0);
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->unique(['business_id', 'date']);
            $table->foreign('business_id')->references('id')->on('businesses')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('llm_cost_daily');
    }
};
