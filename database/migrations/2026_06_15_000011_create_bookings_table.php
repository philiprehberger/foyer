<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bookings', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('conversation_id', 26);
            $table->char('business_id', 26);
            $table->string('customer_phone_e164');
            $table->text('address')->nullable();
            $table->decimal('address_lat', 10, 7)->nullable();
            $table->decimal('address_lng', 10, 7)->nullable();
            $table->string('service_type_key');
            $table->timestampTz('slot_start');
            $table->timestampTz('slot_end');
            $table->string('google_calendar_event_id')->nullable();
            $table->foreignId('confirmed_by')->nullable()->constrained('users');
            $table->timestamp('confirmed_at')->nullable();
            $table->string('status')->default('pending');
            $table->timestamps();

            $table->foreign('business_id')->references('id')->on('businesses')->cascadeOnDelete();
            $table->foreign('conversation_id')->references('id')->on('conversations')->cascadeOnDelete();
            $table->index(['business_id', 'status']);
        });

        // Live bookings cannot overlap inside a business.
        DB::statement(<<<'SQL'
            ALTER TABLE bookings
              ADD CONSTRAINT bookings_no_overlap
              EXCLUDE USING gist (
                business_id WITH =,
                tstzrange(slot_start, slot_end, '[)') WITH &&
              ) WHERE (status IN ('pending', 'confirmed'))
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
