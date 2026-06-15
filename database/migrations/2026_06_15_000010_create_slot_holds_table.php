<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('slot_holds', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('conversation_id', 26);
            $table->char('business_id', 26);
            $table->timestampTz('slot_start');
            $table->timestampTz('slot_end');
            $table->string('service_type_key');
            $table->string('google_calendar_event_id')->nullable();
            $table->string('status')->default('active');
            $table->timestamp('proposed_at')->useCurrent();
            $table->timestamp('expires_at');
            $table->timestamp('released_at')->nullable();
            $table->timestamps();

            $table->foreign('business_id')->references('id')->on('businesses')->cascadeOnDelete();
            $table->foreign('conversation_id')->references('id')->on('conversations')->cascadeOnDelete();
        });

        // The load-bearing guard against double-booking.
        // Two active holds on overlapping ranges for the same business cannot coexist.
        DB::statement(<<<'SQL'
            ALTER TABLE slot_holds
              ADD CONSTRAINT slot_holds_no_overlap
              EXCLUDE USING gist (
                business_id WITH =,
                tstzrange(slot_start, slot_end, '[)') WITH &&
              ) WHERE (status = 'active')
        SQL);

        DB::statement(<<<'SQL'
            CREATE INDEX slot_holds_expiry ON slot_holds (business_id, expires_at)
              WHERE status = 'active'
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('slot_holds');
    }
};
