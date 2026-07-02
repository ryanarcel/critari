<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class SetupTenantMigrations extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tenants:setup-migrations';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Copy baseline Laravel migrations to the tenant migrations folder for schema-per-tenant support';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $tenantMigrationPath = database_path('migrations/tenant');

        if (! File::exists($tenantMigrationPath)) {
            File::makeDirectory($tenantMigrationPath, 0755, true);
            $this->info("Created {$tenantMigrationPath} directory.");
        }

        $migrationsToCopy = [
            '0001_01_01_000000_create_users_table.php',
            '0001_01_01_000001_create_cache_table.php',
            '0001_01_01_000002_create_jobs_table.php',
        ];

        foreach ($migrationsToCopy as $migration) {
            $source = database_path("migrations/{$migration}");
            $destination = "{$tenantMigrationPath}/{$migration}";

            if (File::exists($source)) {
                File::copy($source, $destination);
                $this->info("Copied {$migration} to tenant migrations.");
            } else {
                $this->warn("Source migration {$migration} not found.");
            }
        }

        $this->info('Tenant migrations setup complete!');
    }
}
