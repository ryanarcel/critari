<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureTenantInitialized
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next)
    {
        // If the tenancy helper exists and a tenant is initialized, continue.
        if (function_exists('tenant') && tenant()) {
            return $next($request);
        }

        // If the stancl tenancy manager is bound, prefer the `tenant()` helper check above.
        // Avoid calling non-existent methods on the tenancy manager to prevent fatal errors.

        // Otherwise return a helpful JSON error for AJAX or a plain response for browser.
        if ($request->wantsJson() || $request->isXmlHttpRequest()) {
            return response()->json(['error' => 'Tenant not initialized. Visit via tenant subdomain or initialize tenancy first.'], 400);
        }

        return response('Tenant not initialized. Visit via tenant subdomain or initialize tenancy first.', 400);
    }
}
