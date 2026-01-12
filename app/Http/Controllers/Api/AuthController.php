<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required|string|min:6',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // Check if user exists
        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid credentials'
            ], 401);
        }

        if (!$user->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'Account is inactive'
            ], 403);
        }

        // Record login
        $user->recordLogin();

        // Create token
        $token = $user->createToken('auth-token')->plainTextToken;

        // Get tenant information if user belongs to a tenant
        $tenant = null;
        if ($user->tenant_id) {
            $tenant = Tenant::find($user->tenant_id);
        }

        return response()->json([
            'success' => true,
            'message' => 'Login successful',
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'uuid' => $user->uuid,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role,
                    'tenant_id' => $user->tenant_id,
                    'plan' => $tenant?->plan ?? 'starter',
                    'created_at' => $user->created_at,
                    'updated_at' => $user->updated_at,
                ],
                'tenant' => $tenant ? [
                    'id' => $tenant->id,
                    'name' => $tenant->name,
                    'slug' => $tenant->slug,
                    'plan' => $tenant->plan,
                    'is_active' => $tenant->is_active,
                    'max_users' => $tenant->max_users,
                    'max_vouchers_per_day' => $tenant->max_vouchers_per_day,
                    'metadata' => $tenant->metadata,
                ] : null,
                'token' => $token,
            ]
        ]);
    }

    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'company_name' => 'required|string|max:255',
            'tenant_slug' => 'required|string|max:50|regex:/^[a-z0-9][a-z0-9-]*[a-z0-9]$/|unique:tenants,slug',
            'plan' => 'required|string|in:starter,professional,enterprise',
            'agree_to_terms' => 'required|boolean|accepted',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Check if subdomain is reserved
            $reservedSubdomains = ['www', 'api', 'admin', 'app', 'mail', 'ftp', 'blog', 'shop', 'store'];
            if (in_array($request->tenant_slug, $reservedSubdomains)) {
                return response()->json([
                    'success' => false,
                    'message' => 'This subdomain is reserved and cannot be used.',
                    'errors' => ['tenant_slug' => ['This subdomain is reserved']]
                ], 422);
            }

            // Create tenant first
            $planLimits = $this->getPlanLimits($request->plan);
            
            $tenant = Tenant::create([
                'name' => $request->company_name,
                'slug' => strtolower($request->tenant_slug),
                'email' => $request->email,
                'plan' => $request->plan,
                'is_active' => true,
                'max_users' => $planLimits['max_users'],
                'max_vouchers_per_day' => $planLimits['max_vouchers_per_day'],
                'data_retention_days' => $planLimits['data_retention_days'],
                'billing_cycle' => 'monthly',
                'next_billing_date' => now()->addMonth(),
                'metadata' => [
                    'plan_features' => $planLimits['features'],
                    'created_via' => 'registration',
                    'registration_ip' => $request->ip(),
                    'owner_name' => $request->first_name . ' ' . $request->last_name,
                    'trial_ends_at' => now()->addDays(14)->toISOString(),
                ]
            ]);

            // Create domain for tenant
            $tenant->domains()->create([
                'domain' => $tenant->slug . '.netbillpro.com'
            ]);

            // Create admin user for tenant
            $user = User::create([
                'name' => $request->first_name . ' ' . $request->last_name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'role' => 'admin',
                'is_active' => true,
                'tenant_id' => $tenant->id,
            ]);

            // Create token
            $token = $user->createToken('auth-token')->plainTextToken;

            // Get the tenant URL
            $tenantUrl = "https://{$tenant->slug}.netbillpro.com";

            return response()->json([
                'success' => true,
                'message' => 'Registration successful! Your account has been created.',
                'data' => [
                    'user' => [
                        'id' => $user->id,
                        'uuid' => $user->uuid,
                        'name' => $user->name,
                        'email' => $user->email,
                        'role' => $user->role,
                        'tenant_id' => $user->tenant_id,
                        'plan' => $tenant->plan,
                        'created_at' => $user->created_at,
                        'updated_at' => $user->updated_at,
                    ],
                    'tenant' => [
                        'id' => $tenant->id,
                        'name' => $tenant->name,
                        'slug' => $tenant->slug,
                        'plan' => $tenant->plan,
                        'is_active' => $tenant->is_active,
                        'max_users' => $tenant->max_users,
                        'max_vouchers_per_day' => $tenant->max_vouchers_per_day,
                        'metadata' => $tenant->metadata,
                        'tenant_url' => $tenantUrl,
                        'trial_ends_at' => $tenant->metadata['trial_ends_at'] ?? null,
                        'created_at' => $tenant->created_at,
                    ],
                    'token' => $token,
                    'redirect_url' => $tenantUrl . '/app/dashboard?welcome=true',
                ]
            ], 201);

        } catch (\Exception $e) {
            \Log::error('Registration failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->except(['password', 'password_confirmation'])
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Registration failed. Please try again.',
                'debug' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Logged out successfully'
        ]);
    }

    public function me(Request $request)
    {
        $user = $request->user();
        $tenant = null;

        if ($user->tenant_id) {
            $tenant = Tenant::find($user->tenant_id);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'uuid' => $user->uuid,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role,
                    'tenant_id' => $user->tenant_id,
                    'plan' => $tenant?->plan ?? 'starter',
                    'is_active' => $user->is_active,
                    'last_login_at' => $user->last_login_at,
                    'created_at' => $user->created_at,
                    'updated_at' => $user->updated_at,
                ],
                'tenant' => $tenant ? [
                    'id' => $tenant->id,
                    'name' => $tenant->name,
                    'slug' => $tenant->slug,
                    'plan' => $tenant->plan,
                    'is_active' => $tenant->is_active,
                    'max_users' => $tenant->max_users,
                    'max_vouchers_per_day' => $tenant->max_vouchers_per_day,
                    'metadata' => $tenant->metadata,
                    'usage_stats' => $tenant->getUsageStatistics(),
                ] : null,
            ]
        ]);
    }

    private function getPlanLimits(string $plan): array
    {
        return match($plan) {
            'starter' => [
                'max_users' => 5,
                'max_vouchers_per_day' => 100,
                'data_retention_days' => 90,
                'features' => [
                    'max_customers' => 100,
                    'max_routers' => 2,
                    'has_advanced_sms' => false,
                    'has_multiple_gateways' => false,
                    'has_api_access' => false,
                    'has_custom_branding' => false,
                    'has_white_label' => false,
                    'has_priority_support' => false,
                    'has_advanced_reports' => false,
                ]
            ],
            'professional' => [
                'max_users' => 25,
                'max_vouchers_per_day' => 1000,
                'data_retention_days' => 365,
                'features' => [
                    'max_customers' => 1000,
                    'max_routers' => -1, // unlimited
                    'has_advanced_sms' => true,
                    'has_multiple_gateways' => true,
                    'has_api_access' => true,
                    'has_custom_branding' => true,
                    'has_white_label' => false,
                    'has_priority_support' => true,
                    'has_advanced_reports' => true,
                ]
            ],
            'enterprise' => [
                'max_users' => -1, // unlimited
                'max_vouchers_per_day' => -1, // unlimited
                'data_retention_days' => -1, // unlimited
                'features' => [
                    'max_customers' => -1, // unlimited
                    'max_routers' => -1, // unlimited
                    'has_advanced_sms' => true,
                    'has_multiple_gateways' => true,
                    'has_api_access' => true,
                    'has_custom_branding' => true,
                    'has_white_label' => true,
                    'has_priority_support' => true,
                    'has_advanced_reports' => true,
                ]
            ],
            default => [
                'max_users' => 5,
                'max_vouchers_per_day' => 100,
                'data_retention_days' => 90,
                'features' => []
            ]
        };
    }
}