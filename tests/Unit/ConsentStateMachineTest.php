<?php

namespace Tests\Unit;

use App\Services\ConsentStateMachine;
use PHPUnit\Framework\TestCase;

class ConsentStateMachineTest extends TestCase
{
    public function test_classify_stop_variants(): void
    {
        foreach (['STOP', 'stop', 'Stop', ' STOP ', 'UNSUBSCRIBE', 'CANCEL', 'END', 'QUIT'] as $body) {
            $this->assertSame('stop', ConsentStateMachine::classify($body), "should classify '{$body}' as stop");
        }
    }

    public function test_classify_start_variants(): void
    {
        foreach (['START', 'start', 'YES', 'UNSTOP'] as $body) {
            $this->assertSame('start', ConsentStateMachine::classify($body));
        }
    }

    public function test_classify_help(): void
    {
        $this->assertSame('help', ConsentStateMachine::classify('HELP'));
        $this->assertSame('help', ConsentStateMachine::classify('info'));
    }

    public function test_non_keyword_returns_null(): void
    {
        $this->assertNull(ConsentStateMachine::classify('hi can you clear my kitchen drain'));
        $this->assertNull(ConsentStateMachine::classify('thursday works'));
    }
}
