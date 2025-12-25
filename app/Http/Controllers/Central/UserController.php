<?php

namespace App\Http\Controllers\Central;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class UserController extends Controller
{
    /**
     * Display a listing of users.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $perPage = $request->input('per_page', 20);
            $search = $request->input('search');
            $role = $request->input('role');
            $status = $request->input('status');
            $tenantId = $request->input('tenant_id');
            $sortBy = $request->input('sort_by', 'created_at');
            $sortOrder = $request->input('sort_order', 'desc');

            $query = User::with('tenant');

            // Apply search filter
            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")
                        ->orWhere('uuid', 'like', "%{$search}%");
                });
            }

            // Apply role filter
            if ($role) {
                $query->where('role', $role);
            }

            // Apply status filter
            if ($status === 'active') {
                $query->active();
            } elseif ($status === 'inactive') {
                $query->where('is_active', false);
            }

            // Apply tenant filter
            if ($tenantId) {
                $query->tenantUsers($tenantId);
            }

            // Apply sorting
            $validSortColumns = ['name', 'email', 'role', 'created_at', 'updated_at', 'last_login_at'];
            if (in_array($sortBy, $validSortColumns)) {
                $query->orderBy($sortBy, $sortOrder);
            }

            $users = $query->paginate($perPage);

            // Transform the response
            $transformedUsers = $users->through(function ($user) {
                return [
                    'uuid' => $user->uuid,
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'role' => $user->role,
                    'role_name' => $user->role_name,
                    'is_active' => $user->is_active,
                    'last_login_at' => $user->last_login_at,
                    'created_at' => $user->created_at,
                    'updated_at' => $user->updated_at,
                    'tenant' => $user->tenant ? [
                        'id' => $user->tenant->id,
                        'name' => $user->tenant->name,
                        'slug' => $user->tenant->slug,
                    ] : null,
                    'metadata' => $user->metadata,
                    'permissions' => $this->getUserPermissions($user),
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $transformedUsers,
                'meta' => [
                    'total' => $users->total(),
                    'per_page' => $users->perPage(),
                    'current_page' => $users->currentPage(),
                    'last_page' => $users->lastPage(),
                    'filters' => [
                        'search' => $search,
                        'role' => $role,
                        'status' => $status,
                        'tenant_id' => $tenantId,
                    ],
                    'role_options' => [
                        ['value' => 'admin', 'label' => 'Administrator'],
                        ['value' => 'staff', 'label' => 'Staff'],
                        ['value' => 'user', 'label' => 'User'],
                    ],
                    'stats' => [
                        'total_users' => User::count(),
                        'active_users' => User::active()->count(),
                        'admins' => User::admins()->count(),
                        'staff' => User::staff()->count(),
                    ]
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch users: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch users',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Store a newly created user.
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
            'role' => ['required', 'string', Rule::in(['admin', 'staff', 'user'])],
            'phone' => 'nullable|string|max:20',
            'is_active' => 'boolean',
            'tenant_id' => 'nullable|string|exists:tenants,id',
            'metadata' => 'nullable|array',
            'send_welcome_email' => 'boolean',
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

            // Check if tenant exists if provided
            if (!empty($data['tenant_id'])) {
                $tenant = Tenant::find($data['tenant_id']);
                if (!$tenant) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Specified tenant does not exist'
                    ], 404);
                }
            }

            // Create user
            $user = User::create([
                'uuid' => \Illuminate\Support\Str::uuid()->toString(),
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
                'role' => $data['role'],
                'phone' => $data['phone'] ?? null,
                'is_active' => $data['is_active'] ?? true,
                'tenant_id' => $data['tenant_id'] ?? null,
                'metadata' => $data['metadata'] ?? [],
            ]);

            // Send welcome email if requested
            if ($request->boolean('send_welcome_email')) {
                $this->sendWelcomeEmail($user, $data['password']);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'User created successfully',
                'data' => [
                    'uuid' => $user->uuid,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role,
                    'role_name' => $user->role_name,
                    'is_active' => $user->is_active,
                    'tenant' => $user->tenant ? [
                        'id' => $user->tenant->id,
                        'name' => $user->tenant->name,
                    ] : null,
                    'created_at' => $user->created_at,
                ]
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to create user: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to create user',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Display the specified user.
     */
    public function show(string $uuid): JsonResponse
    {
        try {
            $user = User::with('tenant')->where('uuid', $uuid)->firstOrFail();

            return response()->json([
                'success' => true,
                'data' => [
                    'uuid' => $user->uuid,
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'role' => $user->role,
                    'role_name' => $user->role_name,
                    'is_active' => $user->is_active,
                    'email_verified_at' => $user->email_verified_at,
                    'last_login_at' => $user->last_login_at,
                    'created_at' => $user->created_at,
                    'updated_at' => $user->updated_at,
                    'tenant' => $user->tenant ? [
                        'id' => $user->tenant->id,
                        'name' => $user->tenant->name,
                        'slug' => $user->tenant->slug,
                        'plan' => $user->tenant->plan,
                    ] : null,
                    'metadata' => $user->metadata,
                    'permissions' => $this->getUserPermissions($user),
                    'stats' => $this->getUserStats($user),
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('User not found: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'User not found'
            ], 404);
        }
    }

    /**
     * Update the specified user.
     */
    public function update(Request $request, string $uuid): JsonResponse
    {
        $user = User::where('uuid', $uuid)->firstOrFail();

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'email' => ['sometimes', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'password' => 'nullable|string|min:8|confirmed',
            'role' => ['sometimes', 'string', Rule::in(['admin', 'staff', 'user'])],
            'phone' => 'nullable|string|max:20',
            'is_active' => 'sometimes|boolean',
            'tenant_id' => 'nullable|string|exists:tenants,id',
            'metadata' => 'nullable|array',
            'current_password' => 'required_with:password|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        // Verify current password if changing password
        if ($request->has('password') && !Hash::check($request->input('current_password'), $user->password)) {
            return response()->json([
                'success' => false,
                'errors' => ['current_password' => ['The current password is incorrect']]
            ], 422);
        }

        DB::beginTransaction();
        try {
            $data = $validator->validated();

            // Remove password fields from data if not changing
            if (empty($data['password'])) {
                unset($data['password']);
                unset($data['password_confirmation']);
            } else {
                $data['password'] = Hash::make($data['password']);
            }

            // Remove current_password from data as it's not a user field
            unset($data['current_password']);

            // Check tenant if changing
            if (isset($data['tenant_id']) && $data['tenant_id'] !== $user->tenant_id) {
                $tenant = Tenant::find($data['tenant_id']);
                if (!$tenant) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Specified tenant does not exist'
                    ], 404);
                }
            }

            $user->update($data);

            // Log activity
            Log::info('User updated', [
                'user_uuid' => $user->uuid,
                'updated_by' => auth()->id(),
                'changes' => $data
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'User updated successfully',
                'data' => [
                    'uuid' => $user->uuid,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role,
                    'role_name' => $user->role_name,
                    'is_active' => $user->is_active,
                    'tenant' => $user->tenant ? [
                        'id' => $user->tenant->id,
                        'name' => $user->tenant->name,
                    ] : null,
                ]
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to update user: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to update user',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Remove the specified user.
     */
    public function destroy(string $uuid): JsonResponse
    {
        DB::beginTransaction();
        try {
            $user = User::where('uuid', $uuid)->firstOrFail();

            // Prevent deleting yourself
            if ($user->id === auth()->id()) {
                return response()->json([
                    'success' => false,
                    'message' => 'You cannot delete your own account'
                ], 403);
            }

            // Prevent deleting last admin
            if ($user->role === 'admin') {
                $adminCount = User::where('role', 'admin')->count();
                if ($adminCount <= 1) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Cannot delete the last administrator'
                    ], 403);
                }
            }

            $user->delete();

            Log::info('User deleted', [
                'user_uuid' => $uuid,
                'deleted_by' => auth()->id()
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'User deleted successfully'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to delete user: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete user',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Suspend a user.
     */
    public function suspend(Request $request, string $uuid): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'reason' => 'nullable|string|max:500',
            'duration_days' => 'nullable|integer|min:1|max:365',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();
        try {
            $user = User::where('uuid', $uuid)->firstOrFail();

            // Prevent suspending yourself
            if ($user->id === auth()->id()) {
                return response()->json([
                    'success' => false,
                    'message' => 'You cannot suspend your own account'
                ], 403);
            }

            if (!$user->is_active) {
                return response()->json([
                    'success' => false,
                    'message' => 'User is already suspended'
                ], 400);
            }

            $user->markAsInactive();

            // Update metadata with suspension info
            $metadata = $user->metadata ?? [];
            $metadata['suspended_at'] = now()->toISOString();
            $metadata['suspended_by'] = auth()->id();
            $metadata['suspension_reason'] = $request->input('reason');
            $metadata['suspension_duration_days'] = $request->input('duration_days');

            if ($request->has('duration_days')) {
                $metadata['suspension_ends_at'] = now()->addDays($request->input('duration_days'))->toISOString();
            }

            $user->update(['metadata' => $metadata]);

            // Revoke all tokens if using Sanctum
            if (method_exists($user, 'tokens')) {
                $user->tokens()->delete();
            }

            Log::info('User suspended', [
                'user_uuid' => $user->uuid,
                'suspended_by' => auth()->id(),
                'reason' => $request->input('reason'),
                'duration' => $request->input('duration_days')
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'User suspended successfully',
                'data' => [
                    'uuid' => $user->uuid,
                    'name' => $user->name,
                    'email' => $user->email,
                    'is_active' => $user->is_active,
                    'suspended_at' => $metadata['suspended_at'],
                    'suspension_ends_at' => $metadata['suspension_ends_at'] ?? null,
                    'suspension_reason' => $metadata['suspension_reason'],
                ]
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to suspend user: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to suspend user',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Activate a user.
     */
    public function activate(string $uuid): JsonResponse
    {
        DB::beginTransaction();
        try {
            $user = User::where('uuid', $uuid)->firstOrFail();

            if ($user->is_active) {
                return response()->json([
                    'success' => false,
                    'message' => 'User is already active'
                ], 400);
            }

            $user->markAsActive();

            // Clear suspension metadata
            $metadata = $user->metadata ?? [];
            unset($metadata['suspended_at']);
            unset($metadata['suspended_by']);
            unset($metadata['suspension_reason']);
            unset($metadata['suspension_duration_days']);
            unset($metadata['suspension_ends_at']);

            $metadata['activated_at'] = now()->toISOString();
            $metadata['activated_by'] = auth()->id();

            $user->update(['metadata' => $metadata]);

            Log::info('User activated', [
                'user_uuid' => $user->uuid,
                'activated_by' => auth()->id()
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'User activated successfully',
                'data' => [
                    'uuid' => $user->uuid,
                    'name' => $user->name,
                    'email' => $user->email,
                    'is_active' => $user->is_active,
                    'activated_at' => $metadata['activated_at'],
                ]
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to activate user: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to activate user',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Get user permissions.
     */
    private function getUserPermissions(User $user): array
    {
        $permissions = [];

        switch ($user->role) {
            case 'admin':
                $permissions = ['view_payments', 'manage_vouchers', 'manage_customers', 'view_reports', 'system_settings', 'manage_users', 'manage_tenants'];
                break;
            case 'staff':
                $permissions = ['view_payments', 'manage_vouchers', 'view_customers', 'view_reports'];
                break;
            case 'user':
                $permissions = ['view_payments', 'view_vouchers'];
                break;
        }

        // Add any custom permissions from metadata
        if (isset($user->metadata['custom_permissions'])) {
            $permissions = array_merge($permissions, $user->metadata['custom_permissions']);
        }

        return array_values(array_unique($permissions));
    }

    /**
     * Get user statistics.
     */
    private function getUserStats(User $user): array
    {
        $stats = [
            'total_logins' => 0,
            'last_activity' => null,
            'created_records' => 0,
        ];

        if ($user->tenant_id) {
            try {
                // Get stats from tenant database
                $tenantStats = $user->tenant->run(function () use ($user) {
                    // This would run in the tenant context
                    // You might want to track user activities in a separate table
                    return [
                        'login_count' => \App\Models\LoginActivity::where('user_id', $user->id)->count() ?? 0,
                        'last_activity' => \App\Models\ActivityLog::where('user_id', $user->id)->latest()->first()?->created_at ?? null,
                    ];
                });

                $stats = array_merge($stats, $tenantStats);
            } catch (\Exception $e) {
                Log::warning('Could not fetch tenant stats for user: ' . $user->uuid);
            }
        }

        return $stats;
    }

    /**
     * Send welcome email.
     */
    private function sendWelcomeEmail(User $user, string $password): void
    {
        try {
            // Implement email sending logic
            // Example using Laravel Mail
            /*
            Mail::to($user->email)->send(new WelcomeEmail([
                'name' => $user->name,
                'email' => $user->email,
                'password' => $password,
                'role' => $user->role_name,
                'login_url' => config('app.url') . '/login',
            ]));
            */

            Log::info('Welcome email would be sent to: ' . $user->email);
        } catch (\Exception $e) {
            Log::error('Failed to send welcome email: ' . $e->getMessage());
        }
    }

    /**
     * Additional endpoints that might be useful
     */

    /**
     * Get user's activity log.
     */
    public function activityLog(string $uuid): JsonResponse
    {
        try {
            $user = User::where('uuid', $uuid)->firstOrFail();

            $activities = [];

            // This would typically come from an activity log table
            // For now, return basic info
            $activities = [
                [
                    'action' => 'account_created',
                    'description' => 'User account was created',
                    'timestamp' => $user->created_at,
                    'ip_address' => null,
                ],
                [
                    'action' => 'last_login',
                    'description' => 'Last successful login',
                    'timestamp' => $user->last_login_at,
                    'ip_address' => null,
                ],
            ];

            return response()->json([
                'success' => true,
                'data' => [
                    'user' => [
                        'uuid' => $user->uuid,
                        'name' => $user->name,
                        'email' => $user->email,
                    ],
                    'activities' => $activities,
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch activity log'
            ], 500);
        }
    }

    /**
     * Update user's password (separate endpoint).
     */
    public function updatePassword(Request $request, string $uuid): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'current_password' => 'required|string',
            'new_password' => 'required|string|min:8|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $user = User::where('uuid', $uuid)->firstOrFail();

            // Verify current password
            if (!Hash::check($request->input('current_password'), $user->password)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Current password is incorrect'
                ], 401);
            }

            $user->update([
                'password' => Hash::make($request->input('new_password'))
            ]);

            Log::info('Password updated for user: ' . $user->uuid);

            return response()->json([
                'success' => true,
                'message' => 'Password updated successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to update password: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to update password'
            ], 500);
        }
    }
}
