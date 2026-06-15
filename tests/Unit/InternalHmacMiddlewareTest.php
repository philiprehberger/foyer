<?php

namespace Tests\Unit;

use App\Http\Middleware\InternalHmacMiddleware;
use Illuminate\Http\Request;
use Tests\TestCase;

class InternalHmacMiddlewareTest extends TestCase
{
    public function test_rejects_missing_signature(): void
    {
        config()->set('foyer.internal_secret', 'a'.str_repeat('b', 31));

        $mw = new InternalHmacMiddleware;
        $req = Request::create('/_internal/x', 'POST', content: '{"k":"v"}');

        $resp = $mw->handle($req, fn () => response('ok'));

        $this->assertSame(401, $resp->getStatusCode());
    }

    public function test_rejects_wrong_signature(): void
    {
        config()->set('foyer.internal_secret', 'a'.str_repeat('b', 31));

        $mw = new InternalHmacMiddleware;
        $req = Request::create('/_internal/x', 'POST', content: '{"k":"v"}');
        $req->headers->set('X-Foyer-Internal-Sig', 'deadbeef');

        $resp = $mw->handle($req, fn () => response('ok'));

        $this->assertSame(403, $resp->getStatusCode());
    }

    public function test_accepts_correct_signature(): void
    {
        $secret = 'a'.str_repeat('b', 31);
        config()->set('foyer.internal_secret', $secret);

        $mw = new InternalHmacMiddleware;
        $body = '{"k":"v"}';
        $sig = hash_hmac('sha256', $body, $secret);

        $req = Request::create('/_internal/x', 'POST', content: $body);
        $req->headers->set('X-Foyer-Internal-Sig', $sig);

        $resp = $mw->handle($req, fn () => response('ok'));

        $this->assertSame(200, $resp->getStatusCode());
        $this->assertSame('ok', $resp->getContent());
    }
}
