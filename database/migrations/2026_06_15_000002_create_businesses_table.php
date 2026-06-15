<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('businesses', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('timezone')->default('America/Denver');
            $table->time('quiet_hours_start')->default('21:00:00');
            $table->time('quiet_hours_end')->default('08:00:00');
            $table->string('google_calendar_id')->nullable();
            $table->string('oauth_refresh_token_ref')->nullable();
            $table->string('persona')->default('professional');
            $table->text('system_prompt_suffix')->nullable();
            $table->jsonb('service_area')->default('{}');
            $table->jsonb('business_hours')->default('{}');
            $table->jsonb('blocked_dates')->default('[]');
            $table->integer('min_lead_minutes')->default(60);
            $table->integer('max_lead_days')->default(60);
            $table->string('human_handoff_phone')->nullable();
            $table->float('human_handoff_threshold')->default(0.6);
            $table->bigInteger('cost_ceiling_micros')->default(500000);
            $table->timestamp('kill_switch_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('businesses');
    }
};
