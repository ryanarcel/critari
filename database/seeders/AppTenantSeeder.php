<?php

namespace Database\Seeders;

use App\Models\Tenant;
use Illuminate\Database\Seeder;

class AppTenantSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Tenant::firstOrCreate(['id' => 'app']);

        $tenant->domains()->firstOrCreate(['domain' => 'app']);
    }
}
