<?php

namespace App\Console\Commands;

use App\Models\ApiKey;
use App\Models\Delivery;
use App\Models\Event;
use App\Models\Workspace;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Delete sandbox-workspace data older than the retention window. Wired to
 * the scheduler in routes/console.php so it runs hourly on the EC2 host.
 */
#[Signature('webhook-relay:sweep-sandbox {--hours=24 : Retention window in hours}')]
#[Description('Delete sandbox events, deliveries, and keys older than the retention window.')]
class SweepSandboxCommand extends Command
{
    public function handle(): int
    {
        $hours = (int) $this->option('hours');
        if ($hours < 1) {
            $this->error('--hours must be >= 1');

            return self::FAILURE;
        }

        $workspace = Workspace::where('slug', 'public-sandbox')->where('is_sandbox', true)->first();
        if ($workspace === null) {
            $this->info('No sandbox workspace exists yet — nothing to sweep.');

            return self::SUCCESS;
        }

        $cutoff = now()->subHours($hours);

        $events = Event::where('workspace_id', $workspace->id)
            ->where('created_at', '<', $cutoff)
            ->limit(5000)
            ->delete();

        $deliveries = Delivery::where('workspace_id', $workspace->id)
            ->where('created_at', '<', $cutoff)
            ->limit(5000)
            ->delete();

        $keys = ApiKey::where('workspace_id', $workspace->id)
            ->where('created_at', '<', $cutoff)
            ->limit(5000)
            ->delete();

        $this->line("Sandbox sweep < {$cutoff->toIso8601String()}:");
        $this->line("  events:     {$events}");
        $this->line("  deliveries: {$deliveries}");
        $this->line("  api_keys:   {$keys}");

        return self::SUCCESS;
    }
}
