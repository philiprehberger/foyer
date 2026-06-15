<?php

namespace App\Console\Commands;

use App\Models\Business;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class SeedAdminUser extends Command
{
    protected $signature = 'foyer:seed-admin
                            {email}
                            {--name=Owner}
                            {--password=}
                            {--business-slug= : optional business slug to attach to}';

    protected $description = 'Create or update an admin user account for the Filament panel.';

    public function handle(): int
    {
        $email = (string) $this->argument('email');
        $name = (string) $this->option('name');
        $password = (string) ($this->option('password') ?: bin2hex(random_bytes(10)));
        $slug = $this->option('business-slug');

        $user = User::query()->updateOrCreate(
            ['email' => $email],
            ['name' => $name, 'password' => Hash::make($password)],
        );

        if ($slug) {
            $business = Business::query()->where('slug', $slug)->first();
            if (! $business) {
                $this->error("No business with slug={$slug}");

                return self::FAILURE;
            }
            $business->users()->syncWithoutDetaching([$user->id => ['role' => 'owner']]);
            $this->info("Attached {$email} to business {$slug}");
        }

        $this->info("Admin user {$email} ready.");
        if (! $this->option('password')) {
            $this->warn("Generated password: {$password}");
        }

        return self::SUCCESS;
    }
}
