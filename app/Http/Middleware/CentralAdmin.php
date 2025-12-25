<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CentralAdmin
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // Check if user is a central admin
        if (!$user || (!$user->is_super_admin && $user->role !== 'admin')) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized. Central admin access required.',
                ], 403);
            }

            return redirect()->route('central.login')->with('error', 'Central admin access required.');
        }

        return $next($request);
    }
}
