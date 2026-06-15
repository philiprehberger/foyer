<?php

namespace Tests\Unit;

use App\Http\Middleware\TwilioSignatureMiddleware;
use PHPUnit\Framework\TestCase;

class TwilioSignatureMiddlewareTest extends TestCase
{
    public function test_compute_matches_twilio_spec(): void
    {
        $mw = new TwilioSignatureMiddleware;
        $token = 'auth-token-secret';
        $url = 'https://example.com/v1/sms/inbound';
        $params = ['MessageSid' => 'SM1', 'Body' => 'hi', 'From' => '+15551234567'];

        $sorted = $params;
        ksort($sorted);
        $data = $url;
        foreach ($sorted as $k => $v) {
            $data .= $k.$v;
        }
        $expected = base64_encode(hash_hmac('sha1', $data, $token, true));

        $this->assertSame($expected, $mw->compute($url, $params, $token));
    }

    public function test_handles_array_values(): void
    {
        $mw = new TwilioSignatureMiddleware;
        $token = 'tk';
        $url = 'https://example.com';
        $params = ['x' => ['a', 'b']];

        $expected = base64_encode(hash_hmac('sha1', $url.'x'.json_encode(['a', 'b']), $token, true));
        $this->assertSame($expected, $mw->compute($url, $params, $token));
    }
}
