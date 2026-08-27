<?php

namespace Database\Seeders;

use App\Models\Tenant;
use Illuminate\Database\Seeder;

class AppTenantSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Tenant::firstOrCreate(['id' => 'app']);

        // Create domain for local development
        $tenant->domains()->firstOrCreate(['domain' => 'app']);

        // Create domain for production if APP_DOMAIN is set and different
        $appDomain = env('APP_DOMAIN', 'critari.com');
        if ($appDomain !== 'critari.com') {
            $tenant->domains()->firstOrCreate(['domain' => "app.{$appDomain}"]);
        }
    }
}
