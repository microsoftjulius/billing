<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;

class TenantMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Prevent access from central domains
        $preventAccess = new PreventAccessFromCentralDomains();

        return $preventAccess->handle($request, function ($request) use ($next) {
            // Initialize tenancy
            $initialize = new InitializeTenancyByDomain();
            return $initialize->handle($request, $next);
        });
    }
}

class TenantApiMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // For API, we can use header-based tenancy
        $tenantId = $request->header('X-Tenant-ID')
            ?: $request->get('tenant_id');

        if ($tenantId) {
            $tenant = \App\Models\Tenant::find($tenantId);

            if ($tenant) {
                if (!$tenant->is_active) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Tenant account is suspended',
                    ], 403);
                }

                tenancy()->initialize($tenant);
                return $next($request);
            }

            return response()->json([
                'success' => false,
                'message' => 'Tenant not found',
            ], 404);
        }

        // Fallback to domain-based tenancy
        $preventAccess = new PreventAccessFromCentralDomains();
        return $preventAccess->handle($request, function ($request) use ($next) {
            $initialize = new InitializeTenancyByDomain();
            return $initialize->handle($request, $next);
        });
    }
}
