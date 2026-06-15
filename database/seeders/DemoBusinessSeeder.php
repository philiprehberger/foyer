<?php

namespace Database\Seeders;

use App\Models\Business;
use App\Models\ServiceType;
use Illuminate\Database\Seeder;

/**
 * Seeds the "Anchor Plumbing" demo business that fronts the public live
 * demo at foyer.philiprehberger.com/demo. Idempotent — re-runs replace
 * the row.
 */
class DemoBusinessSeeder extends Seeder
{
    public function run(): void
    {
        $slug = (string) config('foyer.demo.business_slug', 'anchor-plumbing');

        $business = Business::query()->updateOrCreate(
            ['slug' => $slug],
            [
                'name' => 'Anchor Plumbing',
                'timezone' => 'America/Denver',
                'quiet_hours_start' => '21:00:00',
                'quiet_hours_end' => '08:00:00',
                'persona' => 'professional',
                'service_area' => [
                    'type' => 'zip_codes',
                    'codes' => ['80301', '80302', '80303', '80304', '80305', '80306', '80307', '80308', '80309', '80310'],
                ],
                'business_hours' => [
                    'mon' => [['open' => '08:00', 'close' => '17:00']],
                    'tue' => [['open' => '08:00', 'close' => '17:00']],
                    'wed' => [['open' => '08:00', 'close' => '17:00']],
                    'thu' => [['open' => '08:00', 'close' => '17:00']],
                    'fri' => [['open' => '08:00', 'close' => '17:00']],
                ],
                'min_lead_minutes' => 60,
                'max_lead_days' => 30,
                'human_handoff_threshold' => 0.6,
                'cost_ceiling_micros' => 500000,
            ],
        );

        foreach ([
            ['key' => 'drain-clear', 'label' => 'Drain clearing', 'est_duration_min' => 60, 'requires_photos' => false],
            ['key' => 'leak-repair', 'label' => 'Leak repair', 'est_duration_min' => 90, 'requires_photos' => true],
            ['key' => 'water-heater', 'label' => 'Water heater install', 'est_duration_min' => 180, 'requires_photos' => true],
        ] as $row) {
            ServiceType::query()->updateOrCreate(
                ['business_id' => $business->id, 'key' => $row['key']],
                array_merge($row, ['business_id' => $business->id]),
            );
        }
    }
}
