<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => env('SUPERADMIN_EMAIL', 'superadmin@critari.test')],
            [
                'name' => env('SUPERADMIN_NAME', 'Super Admin'),
                'password' => Hash::make(env('SUPERADMIN_PASSWORD', 'password')),
                'is_superadmin' => true,
            ]
        );
    }
}
