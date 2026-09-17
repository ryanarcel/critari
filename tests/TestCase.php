<?php

namespace Tests;

use App\Models\Tenant;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Schema;
use Stancl\Tenancy\Exceptions\TenantDatabaseAlreadyExistsException;
use Stancl\Tenancy\Facades\Tenancy;

abstract class TestCase extends BaseTestCase
{
    protected string $tenantId = 'featuretest';

    protected function initializeTenant(): Tenant
    {
        if (! Schema::hasTable('tenants')) {
            $this->artisan('migrate', ['--force' => true]);
        }

        $tenant = Tenant::find($this->tenantId);

        if (! $tenant) {
            try {
                $tenant = Tenant::create(['id' => $this->tenantId]);
                $tenant->domains()->create(['domain' => $this->tenantId]);
            } catch (TenantDatabaseAlreadyExistsException) {
                $tenant = Tenant::withoutEvents(fn () => Tenant::create(['id' => $this->tenantId]));
                $tenant->domains()->firstOrCreate(['domain' => $this->tenantId]);
            }
        }

        Tenancy::initialize($tenant);

        if (! Schema::hasTable('questions') || Schema::hasColumn('assignments', 'description')) {
            $this->artisan('tenants:migrate', [
                '--tenants' => [$this->tenantId],
                '--force' => true,
            ]);
        }

        return $tenant;
    }

    protected function tenantUrl(string $path): string
    {
        return 'http://'.$this->tenantId.'.localhost'.$path;
    }
}
