<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    protected $connection = 'landlord';

    public $withinTransaction = false;

    public function up(): void
    {
        $exists = DB::connection('landlord')
            ->selectOne("SELECT 1 FROM pg_database WHERE datname = 'tenant_db'");

        if (! $exists) {
            DB::connection('landlord')->unprepared('CREATE DATABASE tenant_db');
        }
    }

    public function down(): void
    {
        DB::connection('landlord')->unprepared('DROP DATABASE IF EXISTS tenant_db');
    }
};
