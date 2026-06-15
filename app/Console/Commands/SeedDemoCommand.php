<?php

namespace App\Console\Commands;

use App\Models\ApiKey;
use App\Models\Workspace;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

#[Signature('webhook-relay:seed-demo {--key-env=test : Either live or test, controls the key prefix}')]
#[Description('Create a demo workspace + API key. Prints the plaintext key once.')]
class SeedDemoCommand extends Command
{
    public function handle(): int
    {
        $env = $this->option('key-env');
        if (! in_array($env, ['live', 'test'], true)) {
            $this->error('--key-env must be live or test.');

            return self::FAILURE;
        }

        $workspace = Workspace::create([
            'name' => 'Demo Workspace',
            'slug' => 'demo-'.Str::lower(Str::random(8)),
        ]);

        [$apiKey, $plaintext] = ApiKey::mint($workspace, $env, name: 'demo seed');

        $this->newLine();
        $this->info('Workspace created.');
        $this->line("  id:   {$workspace->id}");
        $this->line("  name: {$workspace->name}");
        $this->line("  slug: {$workspace->slug}");
        $this->newLine();
        $this->info('API key minted. This is the only time the plaintext is shown:');
        $this->newLine();
        $this->line("  <fg=cyan>{$plaintext}</>");
        $this->newLine();
        $this->line("  prefix:    {$apiKey->prefix}");
        $this->line("  last_four: {$apiKey->last_four}");
        $this->newLine();
        $this->comment('Try it:');
        $this->line('  curl -X POST http://localhost:8000/v1/events \\');
        $this->line('    -H "Authorization: Bearer '.$plaintext.'" \\');
        $this->line('    -H "Content-Type: application/json" \\');
        $this->line('    -d \'{"type":"demo.event","payload":{"hello":"world"}}\'');
        $this->newLine();

        return self::SUCCESS;
    }
}
