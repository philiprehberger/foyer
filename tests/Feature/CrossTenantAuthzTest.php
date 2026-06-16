<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Business;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Permutations of (user × business × endpoint). Each owner-facing endpoint
 * must 403 (not 404, not 500, not leak data) when accessed by a user who
 * does not own the business that owns the resource.
 *
 * This class ratchets up as more endpoints land. The plan calls out ~30
 * assertions for Phase 0; this is the seed.
 */
class CrossTenantAuthzTest extends TestCase
{
    use RefreshDatabase;

    public function test_alice_cannot_see_bobs_conversations(): void
    {
        [$alice, $aliceBiz] = $this->makeUserAndBusiness('Alice');
        [$bob, $bobBiz] = $this->makeUserAndBusiness('Bob');

        $bobConv = Conversation::create([
            'business_id' => $bobBiz->id,
            'customer_phone_e164' => '+15551239999',
            'channel' => 'sms',
            'state' => [],
            'started_at' => now(),
        ]);

        Sanctum::actingAs($alice);

        $this->getJson('/v1/conversations')
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->getJson("/v1/conversations/{$bobConv->id}")
            ->assertForbidden();
    }

    public function test_alice_cannot_confirm_bobs_booking(): void
    {
        [$alice, $aliceBiz] = $this->makeUserAndBusiness('Alice');
        [$bob, $bobBiz] = $this->makeUserAndBusiness('Bob');

        $bobConv = Conversation::create([
            'business_id' => $bobBiz->id,
            'customer_phone_e164' => '+15551238888',
            'channel' => 'sms',
            'state' => [],
            'started_at' => now(),
        ]);

        $booking = Booking::create([
            'conversation_id' => $bobConv->id,
            'business_id' => $bobBiz->id,
            'customer_phone_e164' => '+15551238888',
            'service_type_key' => 'drain-clear',
            'slot_start' => now()->addDay(),
            'slot_end' => now()->addDay()->addHour(),
            'status' => Booking::PENDING,
        ]);

        Sanctum::actingAs($alice);
        $this->postJson(
            "/v1/bookings/{$booking->id}/confirm",
            [],
            ['Idempotency-Key' => 'k1'],
        )->assertForbidden();
    }

    public function test_alice_cannot_toggle_bobs_kill_switch(): void
    {
        [$alice] = $this->makeUserAndBusiness('Alice');
        [, $bobBiz] = $this->makeUserAndBusiness('Bob');

        Sanctum::actingAs($alice);
        $this->postJson("/v1/businesses/{$bobBiz->id}/kill-switch")->assertForbidden();
    }

    private function makeUserAndBusiness(string $label): array
    {
        $user = User::create([
            'name' => $label,
            'email' => strtolower($label).'@example.test',
            'password' => bcrypt('test-password'),
        ]);

        $business = Business::create([
            'name' => "{$label}'s Service",
            'slug' => strtolower($label).'-svc',
            'business_hours' => ['mon' => [['open' => '08:00', 'close' => '17:00']]],
        ]);

        $business->users()->attach($user, ['role' => 'owner']);

        return [$user, $business];
    }
}
