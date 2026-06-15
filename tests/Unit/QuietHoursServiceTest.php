<?php

namespace Tests\Unit;

use App\Models\Business;
use App\Services\QuietHoursService;
use Carbon\CarbonImmutable;
use Tests\TestCase;

class QuietHoursServiceTest extends TestCase
{
    public function test_inside_overnight_quiet_window(): void
    {
        $b = new Business([
            'timezone' => 'America/Denver',
            'quiet_hours_start' => '21:00:00',
            'quiet_hours_end' => '08:00:00',
        ]);
        $svc = new QuietHoursService;

        $at22 = CarbonImmutable::create(2026, 6, 15, 22, 0, 0, 'America/Denver');
        $at7 = CarbonImmutable::create(2026, 6, 15, 7, 30, 0, 'America/Denver');
        $at10 = CarbonImmutable::create(2026, 6, 15, 10, 0, 0, 'America/Denver');

        $this->assertTrue($svc->isQuietNow($b, $at22), 'should be quiet at 22:00');
        $this->assertTrue($svc->isQuietNow($b, $at7), 'should be quiet at 07:30');
        $this->assertFalse($svc->isQuietNow($b, $at10), 'should not be quiet at 10:00');
    }

    public function test_next_allowed_send_lands_at_quiet_end(): void
    {
        $b = new Business([
            'timezone' => 'America/Denver',
            'quiet_hours_start' => '21:00:00',
            'quiet_hours_end' => '08:00:00',
        ]);
        $svc = new QuietHoursService;

        $at23 = CarbonImmutable::create(2026, 6, 15, 23, 0, 0, 'America/Denver');
        $expected = CarbonImmutable::create(2026, 6, 16, 8, 0, 0, 'America/Denver');

        $next = $svc->nextAllowedSend($b, $at23);

        $this->assertEquals($expected->format('Y-m-d H:i'), $next->format('Y-m-d H:i'));
    }
}
