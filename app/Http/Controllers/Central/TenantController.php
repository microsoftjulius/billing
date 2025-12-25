<?php

namespace App\Http\Controllers\Central;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class TenantController extends Controller
{
    /**
     * Display a listing of tenants.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $perPage = $request->input('per_page', 15);
            $search = $request->input('search');
            $plan = $request->input('plan');
            $status = $request->input('status');
            $sortBy = $request->input('sort_by', 'created_at');
            $sortOrder = $request->input('sort_order', 'desc');

            $query = Tenant::query();

            // Apply search filter
            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")
                        ->orWhere('slug', 'like', "%{$search}%");
                });
            }

            // Apply plan filter
            if ($plan) {
                $query->where('plan', $plan);
            }

            // Apply status filter
            if ($status === 'active') {
                $query->active();
            } elseif ($status === 'suspended') {
                $query->where('is_active', false);
            }

            // Apply sorting
            $validSortColumns = ['name', 'email', 'plan', 'created_at', 'updated_at', 'next_billing_date'];
            if (in_array($sortBy, $validSortColumns)) {
                $query->orderBy($sortBy, $sortOrder);
            }

            $tenants = $query->paginate($perPage);

            // Transform the response to include additional data
            $transformedTenants = $tenants->through(function ($tenant) {
                return [
                    'id' => $tenant->id,
                    'name' => $tenant->name,
                    'slug' => $tenant->slug,
                    'email' => $tenant->email,
                    'phone' => $tenant->phone,
                    'address' => $tenant->address,
                    'logo_url' => $tenant->logo ? Storage::url($tenant->logo) : null,
                    'is_active' => $tenant->is_active,
                    'plan' => $tenant->plan,
                    'max_users' => $tenant->max_users,
                    'max_vouchers_per_day' => $tenant->max_vouchers_per_day,
                    'data_retention_days' => $tenant->data_retention_days,
                    'billing_cycle' => $tenant->billing_cycle,
                    'next_billing_date' => $tenant->next_billing_date,
                    'created_at' => $tenant->created_at,
                    'updated_at' => $tenant->updated_at,
                    'domains' => $tenant->domains->pluck('domain'),
                    'metadata' => $tenant->metadata,
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $transformedTenants,
                'meta' => [
                    'total' => $tenants->total(),
                    'per_page' => $tenants->perPage(),
                    'current_page' => $tenants->currentPage(),
                    'last_page' => $tenants->lastPage(),
                    'filters' => [
                        'search' => $search,
                        'plan' => $plan,
                        'status' => $status,
                    ]
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch tenants',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Store a newly created tenant.
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:255|unique:tenants,slug',
            'email' => 'required|email|max:255',
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string',
            'logo' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'plan' => ['required', 'string', Rule::in(['basic', 'premium', 'enterprise', 'custom'])],
            'max_users' => 'required|integer|min:1',
            'max_vouchers_per_day' => 'required|integer|min:1',
            'data_retention_days' => 'required|integer|min:30',
            'billing_cycle' => ['required', 'string', Rule::in(['monthly', 'quarterly', 'yearly'])],
            'next_billing_date' => 'required|date',
            'domains' => 'nullable|array',
            'domains.*' => 'string|max:255',
            'metadata' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();
        try {
            $data = $validator->validated();

            // Handle logo upload
            if ($request->hasFile('logo')) {
                $path = $request->file('logo')->store('tenants/logos', 'public');
                $data['logo'] = $path;
            }

            // Create tenant
            $tenant = Tenant::create([
                'id' => $data['slug'], // Using slug as ID for consistency
                'name' => $data['name'],
                'slug' => $data['slug'],
                'email' => $data['email'],
                'phone' => $data['phone'] ?? null,
                'address' => $data['address'] ?? null,
                'logo' => $data['logo'] ?? null,
                'is_active' => true,
                'plan' => $data['plan'],
                'max_users' => $data['max_users'],
                'max_vouchers_per_day' => $data['max_vouchers_per_day'],
                'data_retention_days' => $data['data_retention_days'],
                'billing_cycle' => $data['billing_cycle'],
                'next_billing_date' => $data['next_billing_date'],
                'metadata' => $data['metadata'] ?? [],
            ]);

            // Add domains if provided
            if (!empty($data['domains'])) {
                foreach ($data['domains'] as $domain) {
                    $tenant->domains()->create(['domain' => $domain]);
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Tenant created successfully',
                'data' => [
                    'id' => $tenant->id,
                    'name' => $tenant->name,
                    'slug' => $tenant->slug,
                    'email' => $tenant->email,
                    'domains' => $tenant->domains->pluck('domain'),
                    'is_active' => $tenant->is_active,
                    'plan' => $tenant->plan,
                ]
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to create tenant',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Display the specified tenant.
     */
    public function show(string $id): JsonResponse
    {
        try {
            $tenant = Tenant::with('domains')->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $tenant->id,
                    'name' => $tenant->name,
                    'slug' => $tenant->slug,
                    'email' => $tenant->email,
                    'phone' => $tenant->phone,
                    'address' => $tenant->address,
                    'logo_url' => $tenant->logo ? Storage::url($tenant->logo) : null,
                    'is_active' => $tenant->is_active,
                    'plan' => $tenant->plan,
                    'max_users' => $tenant->max_users,
                    'max_vouchers_per_day' => $tenant->max_vouchers_per_day,
                    'data_retention_days' => $tenant->data_retention_days,
                    'billing_cycle' => $tenant->billing_cycle,
                    'next_billing_date' => $tenant->next_billing_date,
                    'created_at' => $tenant->created_at,
                    'updated_at' => $tenant->updated_at,
                    'domains' => $tenant->domains->pluck('domain'),
                    'metadata' => $tenant->metadata,
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Tenant not found'
            ], 404);
        }
    }

    /**
     * Update the specified tenant.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $tenant = Tenant::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'slug' => ['sometimes', 'string', 'max:255', Rule::unique('tenants')->ignore($tenant->id)],
            'email' => 'sometimes|email|max:255',
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string',
            'logo' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'plan' => ['sometimes', 'string', Rule::in(['basic', 'premium', 'enterprise', 'custom'])],
            'max_users' => 'sometimes|integer|min:1',
            'max_vouchers_per_day' => 'sometimes|integer|min:1',
            'data_retention_days' => 'sometimes|integer|min:30',
            'billing_cycle' => ['sometimes', 'string', Rule::in(['monthly', 'quarterly', 'yearly'])],
            'next_billing_date' => 'sometimes|date',
            'is_active' => 'sometimes|boolean',
            'metadata' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();
        try {
            $data = $validator->validated();

            // Handle logo upload
            if ($request->hasFile('logo')) {
                // Delete old logo if exists
                if ($tenant->logo) {
                    Storage::disk('public')->delete($tenant->logo);
                }

                $path = $request->file('logo')->store('tenants/logos', 'public');
                $data['logo'] = $path;
            }

            $tenant->update($data);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Tenant updated successfully',
                'data' => [
                    'id' => $tenant->id,
                    'name' => $tenant->name,
                    'slug' => $tenant->slug,
                    'email' => $tenant->email,
                    'is_active' => $tenant->is_active,
                    'plan' => $tenant->plan,
                ]
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to update tenant',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Remove the specified tenant.
     */
    public function destroy(string $id): JsonResponse
    {
        DB::beginTransaction();
        try {
            $tenant = Tenant::findOrFail($id);

            // Delete logo if exists
            if ($tenant->logo) {
                Storage::disk('public')->delete($tenant->logo);
            }

            // Delete the tenant (this will also delete domains via cascade)
            $tenant->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Tenant deleted successfully'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete tenant',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Suspend a tenant.
     */
    public function suspend(Request $request, string $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'reason' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $tenant = Tenant::findOrFail($id);

            if (!$tenant->is_active) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tenant is already suspended'
                ], 400);
            }

            $tenant->suspend($request->input('reason'));

            return response()->json([
                'success' => true,
                'message' => 'Tenant suspended successfully',
                'data' => [
                    'id' => $tenant->id,
                    'name' => $tenant->name,
                    'is_active' => $tenant->is_active,
                    'suspended_at' => $tenant->metadata['suspended_at'] ?? null,
                    'suspension_reason' => $tenant->metadata['suspension_reason'] ?? null,
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to suspend tenant',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Activate a tenant.
     */
    public function activate(string $id): JsonResponse
    {
        try {
            $tenant = Tenant::findOrFail($id);

            if ($tenant->is_active) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tenant is already active'
                ], 400);
            }

            $tenant->activate();

            return response()->json([
                'success' => true,
                'message' => 'Tenant activated successfully',
                'data' => [
                    'id' => $tenant->id,
                    'name' => $tenant->name,
                    'is_active' => $tenant->is_active,
                    'activated_at' => $tenant->metadata['activated_at'] ?? null,
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to activate tenant',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Update tenant's plan.
     */
    public function updatePlan(Request $request, string $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'plan' => ['required', 'string', Rule::in(['basic', 'premium', 'enterprise', 'custom'])],
            'max_users' => 'required|integer|min:1',
            'max_vouchers_per_day' => 'required|integer|min:1',
            'data_retention_days' => 'required|integer|min:30',
            'features' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $tenant = Tenant::findOrFail($id);

            $features = [
                'max_users' => $request->input('max_users'),
                'max_vouchers_per_day' => $request->input('max_vouchers_per_day'),
                'data_retention_days' => $request->input('data_retention_days'),
            ];

            // Merge additional features if provided
            if ($request->has('features')) {
                $features = array_merge($features, $request->input('features'));
            }

            $tenant->updatePlan($request->input('plan'), $features);

            return response()->json([
                'success' => true,
                'message' => 'Tenant plan updated successfully',
                'data' => [
                    'id' => $tenant->id,
                    'name' => $tenant->name,
                    'plan' => $tenant->plan,
                    'max_users' => $tenant->max_users,
                    'max_vouchers_per_day' => $tenant->max_vouchers_per_day,
                    'data_retention_days' => $tenant->data_retention_days,
                    'plan_features' => $tenant->metadata['plan_features'] ?? [],
                    'plan_updated_at' => $tenant->metadata['plan_updated_at'] ?? null,
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update tenant plan',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Get tenant's usage statistics.
     */
    public function usage(string $id): JsonResponse
    {
        try {
            $tenant = Tenant::findOrFail($id);
            $usage = $tenant->getUsageStatistics();

            return response()->json([
                'success' => true,
                'data' => [
                    'tenant_id' => $tenant->id,
                    'tenant_name' => $tenant->name,
                    'plan' => $tenant->plan,
                    'usage' => $usage,
                    'limits' => [
                        'max_users' => $tenant->max_users,
                        'max_vouchers_per_day' => $tenant->max_vouchers_per_day,
                        'data_retention_days' => $tenant->data_retention_days,
                        'storage_limit_mb' => $tenant->metadata['storage_limit_mb'] ?? 1024,
                    ],
                    'can_create_user' => $tenant->canCreateUser(),
                    'can_create_voucher' => $tenant->canCreateVoucher(),
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch tenant usage',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Get tenant's analytics.
     */
    public function analytics(Request $request, string $id): JsonResponse
    {
        try {
            $tenant = Tenant::findOrFail($id);
            $period = $request->input('period', 'month'); // day, week, month, year

            $analytics = $tenant->run(function () use ($period) {
                $now = now();
                $startDate = match ($period) {
                    'day' => $now->startOfDay(),
                    'week' => $now->startOfWeek(),
                    'month' => $now->startOfMonth(),
                    'year' => $now->startOfYear(),
                    default => $now->subDays(30),
                };

                return [
                    'period' => $period,
                    'date_range' => [
                        'start' => $startDate->toISOString(),
                        'end' => $now->toISOString(),
                    ],
                    'user_growth' => $this->calculateUserGrowth($startDate, $now),
                    'voucher_activity' => $this->calculateVoucherActivity($startDate, $now),
                    'payment_metrics' => $this->calculatePaymentMetrics($startDate, $now),
                    'active_users' => $this->getActiveUsers($startDate, $now),
                ];
            });

            return response()->json([
                'success' => true,
                'data' => [
                    'tenant_id' => $tenant->id,
                    'tenant_name' => $tenant->name,
                    'analytics_period' => $period,
                    'analytics' => $analytics,
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch tenant analytics',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Calculate user growth for analytics.
     */
    private function calculateUserGrowth($startDate, $endDate): array
    {
        $users = \App\Models\User::whereBetween('created_at', [$startDate, $endDate])
            ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        $totalGrowth = \App\Models\User::where('created_at', '<=', $endDate)->count();
        $previousCount = \App\Models\User::where('created_at', '<', $startDate)->count();
        $growthRate = $previousCount > 0
            ? (($totalGrowth - $previousCount) / $previousCount) * 100
            : 100;

        return [
            'total_users' => $totalGrowth,
            'new_users_count' => $users->sum('count'),
            'growth_rate' => round($growthRate, 2),
            'daily_data' => $users->pluck('count', 'date'),
        ];
    }

    /**
     * Calculate voucher activity for analytics.
     */
    private function calculateVoucherActivity($startDate, $endDate): array
    {
        $vouchers = \App\Models\Voucher::whereBetween('created_at', [$startDate, $endDate])
            ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        $redeemed = \App\Models\Voucher::whereBetween('created_at', [$startDate, $endDate])
            ->where('is_redeemed', true)
            ->count();

        $total = $vouchers->sum('count');
        $redemptionRate = $total > 0 ? ($redeemed / $total) * 100 : 0;

        return [
            'total_vouchers' => $total,
            'redeemed_vouchers' => $redeemed,
            'redemption_rate' => round($redemptionRate, 2),
            'daily_data' => $vouchers->pluck('count', 'date'),
        ];
    }

    /**
     * Calculate payment metrics for analytics.
     */
    private function calculatePaymentMetrics($startDate, $endDate): array
    {
        $payments = \App\Models\Payment::whereBetween('created_at', [$startDate, $endDate])
            ->where('status', 'completed')
            ->selectRaw('DATE(created_at) as date, SUM(amount) as revenue, COUNT(*) as count')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        return [
            'total_revenue' => $payments->sum('revenue'),
            'total_transactions' => $payments->sum('count'),
            'average_transaction_value' => $payments->sum('count') > 0
                ? $payments->sum('revenue') / $payments->sum('count')
                : 0,
            'daily_data' => [
                'revenue' => $payments->pluck('revenue', 'date'),
                'transactions' => $payments->pluck('count', 'date'),
            ],
        ];
    }

    public function search(){
        // the search
    }

    public function checkAvailability(){
        //check availability
    }

    /**
     * Get active users for analytics.
     */
    private function getActiveUsers($startDate, $endDate): array
    {
        $activeUsers = \App\Models\User::whereHas('activities', function ($query) use ($startDate, $endDate) {
            $query->whereBetween('created_at', [$startDate, $endDate]);
        })->count();

        $totalUsers = \App\Models\User::count();
        $activeRate = $totalUsers > 0 ? ($activeUsers / $totalUsers) * 100 : 0;

        return [
            'active_users' => $activeUsers,
            'total_users' => $totalUsers,
            'active_rate' => round($activeRate, 2),
        ];
    }
}
