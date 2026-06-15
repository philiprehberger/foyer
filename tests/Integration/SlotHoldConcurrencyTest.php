<?php

namespace Tests\Integration;

use App\Models\Business;
use App\Models\Conversation;
use App\Services\Calendar\FakeCalendarConnector;
use App\Services\SlotAlreadyHeldException;
use App\Services\SlotHoldService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The load-bearing guard: two parallel `claim()` calls for the same slot
 * must result in exactly one success and one SlotAlreadyHeldException.
 *
 * Requires Postgres + btree_gist; skipped when the test DB is sqlite (the
 * EXCLUDE constraint syntax is Postgres-only). CI runs both: a fast lane
 * against sqlite for everything else, a slow lane against Postgres for
 * this and SlotHoldExclusionConstraintTest.
 */
class SlotHoldConcurrencyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        if (DB::connection()->getDriverName() !== 'pgsql') {
            $this->markTestSkipped('Slot-hold EXCLUDE constraint is Postgres-only; skip on sqlite.');
        }
    }

    public function test_two_parallel_claims_yield_one_winner(): void
    {
        $biz = Business::create([
            'name' => 'Anchor', 'slug' => 'anchor-test',
            'business_hours' => ['mon' => [['open' => '08:00', 'close' => '17:00']]],
        ]);
        $convA = Conversation::create([
            'business_id' => $biz->id, 'customer_phone_e164' => '+15551110001',
            'channel' => 'sms', 'state' => [], 'started_at' => now(),
        ]);
        $convB = Conversation::create([
            'business_id' => $biz->id, 'customer_phone_e164' => '+15551110002',
            'channel' => 'sms', 'state' => [], 'started_at' => now(),
        ]);

        $start = CarbonImmutable::create(2026, 6, 16, 10, 0, 0, 'UTC');
        $end = $start->addHour();

        $svc = new SlotHoldService(new FakeCalendarConnector);

        $svc->claim($convA, $biz, $start, $end, 'drain-clear');

        $this->expectException(SlotAlreadyHeldException::class);
        $svc->claim($convB, $biz, $start, $end, 'drain-clear');
    }

    public function test_adjacent_slots_both_succeed(): void
    {
        $biz = Business::create([
            'name' => 'Anchor', 'slug' => 'anchor-test',
            'business_hours' => ['mon' => [['open' => '08:00', 'close' => '17:00']]],
        ]);
        $convA = Conversation::create([
            'business_id' => $biz->id, 'customer_phone_e164' => '+15551110001',
            'channel' => 'sms', 'state' => [], 'started_at' => now(),
        ]);
        $convB = Conversation::create([
            'business_id' => $biz->id, 'customer_phone_e164' => '+15551110002',
            'channel' => 'sms', 'state' => [], 'started_at' => now(),
        ]);

        $svc = new SlotHoldService(new FakeCalendarConnector);
        $start1 = CarbonImmutable::create(2026, 6, 16, 10, 0, 0, 'UTC');
        $end1 = $start1->addHour();
        $start2 = $end1; // touching but not overlapping (range is [) — half-open)
        $end2 = $start2->addHour();

        $h1 = $svc->claim($convA, $biz, $start1, $end1, 'x');
        $h2 = $svc->claim($convB, $biz, $start2, $end2, 'x');

        $this->assertNotNull($h1->id);
        $this->assertNotNull($h2->id);
        $this->assertNotSame($h1->id, $h2->id);
    }
}
