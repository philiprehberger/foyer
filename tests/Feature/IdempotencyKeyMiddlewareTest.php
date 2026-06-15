<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Business;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class IdempotencyKeyMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    public function test_double_click_collapses_to_one_confirm(): void
    {
        $user = User::create([
            'name' => 'Owner',
            'email' => 'owner@example.test',
            'password' => bcrypt('p'),
        ]);

        $biz = Business::create([
            'name' => 'Owner Plumbing',
            'slug' => 'owner-plumbing',
            'business_hours' => ['mon' => [['open' => '08:00', 'close' => '17:00']]],
        ]);
        $biz->users()->attach($user, ['role' => 'owner']);

        $conv = Conversation::create([
            'business_id' => $biz->id,
            'customer_phone_e164' => '+15555550199',
            'channel' => 'sms',
            'state' => [],
            'started_at' => now(),
        ]);

        $booking = Booking::create([
            'conversation_id' => $conv->id,
            'business_id' => $biz->id,
            'customer_phone_e164' => '+15555550199',
            'service_type_key' => 'drain-clear',
            'slot_start' => now()->addDay(),
            'slot_end' => now()->addDay()->addHour(),
            'status' => Booking::PENDING,
        ]);

        Sanctum::actingAs($user);

        $first = $this->postJson(
            "/v1/bookings/{$booking->id}/confirm",
            [],
            ['Idempotency-Key' => 'click-1'],
        );

        $second = $this->postJson(
            "/v1/bookings/{$booking->id}/confirm",
            [],
            ['Idempotency-Key' => 'click-1'],
        );

        $first->assertOk();
        $second->assertOk();
        $second->assertHeader('X-Idempotent-Replayed', 'true');

        // Booking should be confirmed exactly once.
        $this->assertSame(Booking::CONFIRMED, $booking->fresh()->status);
    }

    public function test_missing_idempotency_key_400s(): void
    {
        $user = User::create([
            'name' => 'X', 'email' => 'x@example.test', 'password' => bcrypt('p'),
        ]);
        $biz = Business::create([
            'name' => 'X', 'slug' => 'x',
            'business_hours' => ['mon' => [['open' => '08:00', 'close' => '17:00']]],
        ]);
        $biz->users()->attach($user, ['role' => 'owner']);

        $conv = Conversation::create([
            'business_id' => $biz->id,
            'customer_phone_e164' => '+15555550100',
            'channel' => 'sms', 'state' => [], 'started_at' => now(),
        ]);
        $booking = Booking::create([
            'conversation_id' => $conv->id,
            'business_id' => $biz->id,
            'customer_phone_e164' => '+15555550100',
            'service_type_key' => 'x',
            'slot_start' => now()->addDay(),
            'slot_end' => now()->addDay()->addHour(),
            'status' => Booking::PENDING,
        ]);

        Sanctum::actingAs($user);
        $this->postJson("/v1/bookings/{$booking->id}/confirm")->assertStatus(400);
    }
}
