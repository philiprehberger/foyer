<?php

namespace Tests\Feature;

use App\Jobs\DispatchAgentTurn;
use App\Models\Business;
use App\Models\ConsentState;
use App\Models\PhoneNumber;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class SmsInboundControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Bypass the Twilio signature middleware for tests by clearing the
        // auth token, which causes the middleware to fail with 503 — so we
        // call the controller-equivalent path by faking the middleware off
        // via env config.
        config()->set('services.twilio.auth_token', 'test-token-shared');

        // The webhook dispatches DispatchAgentTurn synchronously under the
        // sync queue driver, which then POSTs to the FastAPI worker. CI has
        // no worker — Bus::fake captures dispatches without running handle().
        Bus::fake();
    }

    public function test_stop_keyword_writes_consent_state_and_no_agent_dispatch(): void
    {
        $biz = $this->makeBusiness('+15555550199');

        $resp = $this->withoutMiddleware(\App\Http\Middleware\TwilioSignatureMiddleware::class)
            ->post('/v1/sms/inbound', [
                'MessageSid' => 'SM-stop-1',
                'From' => '+15551112222',
                'To' => '+15555550199',
                'Body' => 'STOP',
                'NumMedia' => 0,
            ]);

        $resp->assertStatus(200);
        $this->assertTrue(
            ConsentState::isStopped('+15551112222', '+15555550199'),
            'STOP should put the (customer, twilio_number) pair into stopped state',
        );

        $this->assertDatabaseMissing('messages', ['external_id' => 'SM-stop-1']);
    }

    public function test_duplicate_messagesid_does_not_double_dispatch(): void
    {
        $biz = $this->makeBusiness('+15555550100');

        $payload = [
            'MessageSid' => 'SM-dupe-1',
            'From' => '+15553334444',
            'To' => '+15555550100',
            'Body' => 'hi can you clear my drain',
            'NumMedia' => 0,
        ];

        $this->withoutMiddleware(\App\Http\Middleware\TwilioSignatureMiddleware::class)
            ->post('/v1/sms/inbound', $payload)->assertStatus(200);

        $this->withoutMiddleware(\App\Http\Middleware\TwilioSignatureMiddleware::class)
            ->post('/v1/sms/inbound', $payload)->assertStatus(200);

        $this->assertSame(1, \DB::table('messages')->where('external_id', 'SM-dupe-1')->count());

        // The whole point of the dedup gate: the first inbound dispatches a
        // single AgentTurn, the second short-circuits at the unique index.
        Bus::assertDispatchedTimes(DispatchAgentTurn::class, 1);
    }

    private function makeBusiness(string $twilioE164): Business
    {
        $biz = Business::create([
            'name' => 'Test', 'slug' => 'test-biz-'.bin2hex(random_bytes(3)),
            'timezone' => 'America/Denver',
            'business_hours' => ['mon' => [['open' => '08:00', 'close' => '17:00']]],
        ]);
        PhoneNumber::create([
            'business_id' => $biz->id,
            'number_e164' => $twilioE164,
            'status' => 'active',
            'provisioned_at' => now(),
        ]);

        return $biz;
    }
}
