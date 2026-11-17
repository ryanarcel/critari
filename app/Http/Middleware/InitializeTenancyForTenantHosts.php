<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Stancl\Tenancy\Contracts\TenantCouldNotBeIdentifiedException;
use Stancl\Tenancy\Exceptions\NotASubdomainException;
use Stancl\Tenancy\Middleware\InitializeTenancyBySubdomain;
use Symfony\Component\HttpFoundation\Response;

class InitializeTenancyForTenantHosts
{
    /**
     * Initialize tenancy for tenant subdomains on every web request,
     * including /login, so auth uses tenant users instead of landlord users.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (in_array($request->getHost(), config('tenancy.central_domains'), true)) {
            return $next($request);
        }

        if (function_exists('tenant') && tenant()) {
            return $next($request);
        }

        try {
            return app(InitializeTenancyBySubdomain::class)->handle($request, $next);
        } catch (NotASubdomainException|TenantCouldNotBeIdentifiedException) {
            return $next($request);
        }
    }
}
