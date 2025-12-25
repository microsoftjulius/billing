<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TenantAdmin
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // Check if user is a tenant admin
        if (!$user || $user->role !== 'admin') {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized. Tenant admin access required.',
                ], 403);
            }

            return redirect()->route('tenant.login')->with('error', 'Tenant admin access required.');
        }

        return $next($request);
    }
}
