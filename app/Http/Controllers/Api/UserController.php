<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Role;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Str;

class UserController extends Controller
{
    private ?Tenant $currentTenant;

    public function __construct()
    {
        $this->currentTenant = $this->resolveTenant();
    }

    /**
     * Resolve the current tenant from the request
     */
    private function resolveTenant(): ?Tenant
    {
        // Method 1: From header (for API calls)
        if (request()->hasHeader('X-Tenant-ID')) {
            return Tenant::where('uuid', request()->header('X-Tenant-ID'))->first();
        }

        // Method 2: From subdomain (for web calls)
        $host = request()->getHost();
        $subdomain = explode('.', $host)[0];

        if ($subdomain && $subdomain !== 'www' && $subdomain !== 'api') {
            return Tenant::where('subdomain', $subdomain)->first();
        }

        // Method 3: From request parameter (for shared routes)
        if (request()->has('tenant_id')) {
            return Tenant::where('uuid', request()->get('tenant_id'))->first();
        }

        // Method 4: From authenticated user (if applicable)
        if (auth()->check() && method_exists(auth()->user(), 'tenant')) {
            return auth()->user()->tenant;
        }

        return null; // No tenant resolved
    }

    /**
     * Display a listing of users for the tenant
     */
    public function index(Request $request): JsonResponse
    {
        try {
            // Check if user is admin for this tenant
            if (!$this->isTenantAdmin()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized. Admin access required.'
                ], 403);
            }

            $perPage = min($request->get('per_page', 15), 100);
            $page = $request->get('page', 1);

            $query = User::query()
                ->where('tenant_id', $this->currentTenant->id)
                ->with(['roles'])
                ->orderBy('created_at', 'desc');

            // Apply filters
            if ($request->has('name')) {
                $query->where('name', 'LIKE', '%' . $request->get('name') . '%');
            }

            if ($request->has('email')) {
                $query->where('email', 'LIKE', '%' . $request->get('email') . '%');
            }

            if ($request->has('status')) {
                $query->where('status', $request->get('status'));
            }

            if ($request->has('role')) {
                $query->whereHas('roles', function ($q) use ($request) {
                    $q->where('name', $request->get('role'));
                });
            }

            if ($request->has('is_active')) {
                $query->where('is_active', $request->get('is_active') === 'true');
            }

            if ($request->has('start_date')) {
                $query->whereDate('created_at', '>=', $request->get('start_date'));
            }

            if ($request->has('end_date')) {
                $query->whereDate('created_at', '<=', $request->get('end_date'));
            }

            $users = $query->paginate($perPage, ['*'], 'page', $page);

            $response = [
                'success' => true,
                'tenant' => [
                    'id' => $this->currentTenant->id,
                    'code' => $this->currentTenant->code,
                    'name' => $this->currentTenant->name,
                ],
                'data' => [
                    'users' => $users->map(function ($user) {
                        return $this->formatUserResponse($user);
                    })->toArray(),
                    'pagination' => [
                        'total' => $users->total(),
                        'per_page' => $users->perPage(),
                        'current_page' => $users->currentPage(),
                        'last_page' => $users->lastPage(),
                        'from' => $users->firstItem(),
                        'to' => $users->lastItem(),
                    ],
                    'summary' => [
                        'total_users' => $users->total(),
                        'active_users' => $users->where('is_active', true)->count(),
                        'admin_users' => $users->filter(function ($user) {
                            return $user->hasRole('admin');
                        })->count(),
                        'staff_users' => $users->filter(function ($user) {
                            return $user->hasRole('staff');
                        })->count(),
                        'suspended_users' => $users->where('status', 'suspended')->count(),
                        'pending_users' => $users->where('status', 'pending')->count(),
                    ]
                ]
            ];

            return response()->json($response);

        } catch (\Exception $e) {
            Log::channel('user')->error('Failed to fetch users', [
                'tenant_id' => $this->currentTenant?->id,
                'error' => $e->getMessage(),
                'filters' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch users',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Store a newly created user for the tenant
     */
    public function store(Request $request): JsonResponse
    {
        try {
            // Check if user is admin for this tenant
            if (!$this->isTenantAdmin()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized. Admin access required.'
                ], 403);
            }

            // Validate request
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'email' => 'required|string|email|max:255|unique:users,email',
                'phone' => 'nullable|string|max:20',
                'password' => 'required|string|min:8|confirmed',
                'role' => 'required|string|in:admin,staff,viewer',
                'status' => 'sometimes|string|in:active,pending,suspended',
                'is_active' => 'boolean',
                'permissions' => 'nullable|array',
                'metadata' => 'nullable|array',
            ]);

            if ($validator->fails()) {
                throw new ValidationException($validator);
            }

            $userData = $request->only(['name', 'email', 'phone', 'status', 'is_active', 'permissions', 'metadata']);
            $role = $request->get('role');
            $password = $request->get('password');

            // Set defaults
            $userData['status'] = $userData['status'] ?? 'active';
            $userData['is_active'] = $userData['is_active'] ?? true;
            $userData['password'] = Hash::make($password);
            $userData['tenant_id'] = $this->currentTenant->id;
            $userData['uuid'] = Str::orderedUuid();
            $userData['email_verified_at'] = now();

            // Add metadata
            $userData['metadata'] = array_merge($userData['metadata'] ?? [], [
                'created_by' => auth()->id(),
                'created_at' => now()->toISOString(),
                'tenant_id' => $this->currentTenant->id,
                'tenant_code' => $this->currentTenant->code,
            ]);

            // Create user
            $user = User::create($userData);

            // Assign role
            $roleModel = Role::where('name', $role)->first();
            if ($roleModel) {
                $user->roles()->attach($roleModel);
            }

            // Assign permissions if provided
            if ($request->has('permissions')) {
                $user->syncPermissions($request->get('permissions'));
            }

            Log::channel('user')->info('User created for tenant', [
                'user_id' => $user->id,
                'email' => $user->email,
                'role' => $role,
                'tenant_id' => $this->currentTenant->id,
                'created_by' => auth()->id(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'User created successfully',
                'data' => $this->formatUserResponse($user)
            ], 201);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);

        } catch (\Exception $e) {
            Log::channel('user')->error('Failed to create user', [
                'tenant_id' => $this->currentTenant?->id,
                'error' => $e->getMessage(),
                'data' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to create user',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Display the specified user
     */
    public function show(string $id): JsonResponse
    {
        try {
            // Check if user is admin for this tenant
            if (!$this->isTenantAdmin()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized. Admin access required.'
                ], 403);
            }

            $user = User::with(['roles', 'permissions'])
                ->where('tenant_id', $this->currentTenant->id)
                ->where(function ($q) use ($id) {
                    $q->where('id', $id)->orWhere('uuid', $id);
                })
                ->firstOrFail();

            return response()->json([
                'success' => true,
                'tenant' => [
                    'id' => $this->currentTenant->id,
                    'code' => $this->currentTenant->code,
                    'name' => $this->currentTenant->name,
                ],
                'data' => $this->formatUserResponse($user, true)
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'User not found'
            ], 404);

        } catch (\Exception $e) {
            Log::channel('user')->error('Failed to fetch user', [
                'user_id' => $id,
                'tenant_id' => $this->currentTenant?->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch user details',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Update the specified user
     */
    public function update(Request $request, string $id): JsonResponse
    {
        try {
            // Check if user is admin for this tenant
            if (!$this->isTenantAdmin()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized. Admin access required.'
                ], 403);
            }

            $user = User::where('tenant_id', $this->currentTenant->id)
                ->where(function ($q) use ($id) {
                    $q->where('id', $id)->orWhere('uuid', $id);
                })
                ->firstOrFail();

            // Prevent self-update of role/status by non-super-admin
            $isSelfUpdate = $user->id === auth()->id();
            $isSuperAdmin = auth()->user()->hasRole('super-admin');

            // Validate request
            $validatorRules = [
                'name' => 'sometimes|required|string|max:255',
                'email' => 'sometimes|required|string|email|max:255|unique:users,email,' . $user->id,
                'phone' => 'nullable|string|max:20',
                'password' => 'sometimes|string|min:8|confirmed',
                'status' => 'sometimes|string|in:active,pending,suspended',
                'is_active' => 'boolean',
                'permissions' => 'nullable|array',
                'metadata' => 'nullable|array',
            ];

            // Only allow role update for super-admin or if not self-update
            if (!$isSelfUpdate || $isSuperAdmin) {
                $validatorRules['role'] = 'sometimes|string|in:admin,staff,viewer';
            }

            $validator = Validator::make($request->all(), $validatorRules);

            if ($validator->fails()) {
                throw new ValidationException($validator);
            }

            $updateData = $request->only(['name', 'email', 'phone', 'status', 'is_active']);

            // Update password if provided
            if ($request->has('password')) {
                $updateData['password'] = Hash::make($request->get('password'));
            }

            // Handle metadata update
            if ($request->has('metadata')) {
                $currentMetadata = $user->metadata ?? [];
                $updateData['metadata'] = array_merge($currentMetadata, $request->get('metadata'));
            }

            $user->update($updateData);

            // Update role if allowed
            if ($request->has('role') && (!$isSelfUpdate || $isSuperAdmin)) {
                $role = $request->get('role');
                $roleModel = Role::where('name', $role)->first();
                if ($roleModel) {
                    $user->roles()->sync([$roleModel->id]);
                }
            }

            // Update permissions if provided
            if ($request->has('permissions')) {
                $user->syncPermissions($request->get('permissions'));
            }

            Log::channel('user')->info('User updated', [
                'user_id' => $user->id,
                'email' => $user->email,
                'tenant_id' => $this->currentTenant->id,
                'updated_by' => auth()->id(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'User updated successfully',
                'data' => $this->formatUserResponse($user, true)
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'User not found'
            ], 404);

        } catch (\Exception $e) {
            Log::channel('user')->error('Failed to update user', [
                'user_id' => $id,
                'tenant_id' => $this->currentTenant?->id,
                'error' => $e->getMessage(),
                'data' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update user',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Remove the specified user
     */
    public function destroy(string $id): JsonResponse
    {
        try {
            // Check if user is admin for this tenant
            if (!$this->isTenantAdmin()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized. Admin access required.'
                ], 403);
            }

            $user = User::where('tenant_id', $this->currentTenant->id)
                ->where(function ($q) use ($id) {
                    $q->where('id', $id)->orWhere('uuid', $id);
                })
                ->firstOrFail();

            // Prevent self-deletion
            if ($user->id === auth()->id()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete your own account'
                ], 400);
            }

            // Check if user has any critical data before deletion
            $hasCriticalData = $this->checkUserCriticalData($user);

            if ($hasCriticalData) {
                // Soft delete instead
                $user->update([
                    'is_active' => false,
                    'status' => 'suspended',
                    'deleted_at' => now(),
                ]);

                Log::channel('user')->info('User soft deleted (has critical data)', [
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'tenant_id' => $this->currentTenant->id,
                    'deleted_by' => auth()->id(),
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'User deactivated (has critical data)',
                    'data' => [
                        'id' => $user->id,
                        'status' => 'deactivated',
                        'deactivated_at' => now()->toISOString(),
                    ]
                ]);
            }

            // Actually delete the user
            $userId = $user->id;
            $userEmail = $user->email;
            $user->delete();

            Log::channel('user')->info('User deleted', [
                'user_id' => $userId,
                'email' => $userEmail,
                'tenant_id' => $this->currentTenant->id,
                'deleted_by' => auth()->id(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'User deleted successfully',
                'data' => [
                    'id' => $userId,
                    'status' => 'deleted',
                    'deleted_at' => now()->toISOString(),
                ]
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'User not found'
            ], 404);

        } catch (\Exception $e) {
            Log::channel('user')->error('Failed to delete user', [
                'user_id' => $id,
                'tenant_id' => $this->currentTenant?->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete user',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Suspend a user
     */
    public function suspend(Request $request, string $id): JsonResponse
    {
        try {
            // Check if user is admin for this tenant
            if (!$this->isTenantAdmin()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized. Admin access required.'
                ], 403);
            }

            $user = User::where('tenant_id', $this->currentTenant->id)
                ->where(function ($q) use ($id) {
                    $q->where('id', $id)->orWhere('uuid', $id);
                })
                ->firstOrFail();

            // Prevent self-suspension
            if ($user->id === auth()->id()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot suspend your own account'
                ], 400);
            }

            // Validate suspension reason
            $validator = Validator::make($request->all(), [
                'reason' => 'nullable|string|max:500',
                'duration_days' => 'nullable|integer|min:1|max:365',
            ]);

            if ($validator->fails()) {
                throw new ValidationException($validator);
            }

            $reason = $request->get('reason', 'Administrative suspension');
            $durationDays = $request->get('duration_days');

            // Update user status
            $user->update([
                'status' => 'suspended',
                'is_active' => false,
                'suspended_at' => now(),
                'suspended_by' => auth()->id(),
                'suspension_reason' => $reason,
                'suspension_end_at' => $durationDays ? now()->addDays($durationDays) : null,
            ]);

            // Add to metadata
            $metadata = $user->metadata ?? [];
            $metadata['suspensions'][] = [
                'date' => now()->toISOString(),
                'reason' => $reason,
                'duration_days' => $durationDays,
                'suspended_by' => auth()->id(),
            ];
            $user->update(['metadata' => $metadata]);

            Log::channel('user')->info('User suspended', [
                'user_id' => $user->id,
                'email' => $user->email,
                'reason' => $reason,
                'duration_days' => $durationDays,
                'tenant_id' => $this->currentTenant->id,
                'suspended_by' => auth()->id(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'User suspended successfully',
                'data' => [
                    'id' => $user->id,
                    'email' => $user->email,
                    'status' => 'suspended',
                    'suspended_at' => $user->suspended_at?->toISOString(),
                    'suspension_end_at' => $user->suspension_end_at?->toISOString(),
                    'reason' => $reason,
                ]
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'User not found'
            ], 404);

        } catch (\Exception $e) {
            Log::channel('user')->error('Failed to suspend user', [
                'user_id' => $id,
                'tenant_id' => $this->currentTenant?->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to suspend user',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Activate a user
     */
    public function activate(Request $request, string $id): JsonResponse
    {
        try {
            // Check if user is admin for this tenant
            if (!$this->isTenantAdmin()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized. Admin access required.'
                ], 403);
            }

            $user = User::where('tenant_id', $this->currentTenant->id)
                ->where(function ($q) use ($id) {
                    $q->where('id', $id)->orWhere('uuid', $id);
                })
                ->firstOrFail();

            // Activate user
            $user->update([
                'status' => 'active',
                'is_active' => true,
                'activated_at' => now(),
                'activated_by' => auth()->id(),
                'suspension_end_at' => null,
            ]);

            // Add to metadata
            $metadata = $user->metadata ?? [];
            $metadata['activations'][] = [
                'date' => now()->toISOString(),
                'activated_by' => auth()->id(),
            ];
            $user->update(['metadata' => $metadata]);

            Log::channel('user')->info('User activated', [
                'user_id' => $user->id,
                'email' => $user->email,
                'tenant_id' => $this->currentTenant->id,
                'activated_by' => auth()->id(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'User activated successfully',
                'data' => [
                    'id' => $user->id,
                    'email' => $user->email,
                    'status' => 'active',
                    'activated_at' => $user->activated_at?->toISOString(),
                    'is_active' => true,
                ]
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'User not found'
            ], 404);

        } catch (\Exception $e) {
            Log::channel('user')->error('Failed to activate user', [
                'user_id' => $id,
                'tenant_id' => $this->currentTenant?->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to activate user',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Change user role
     */
    public function changeRole(Request $request, string $id): JsonResponse
    {
        try {
            // Check if user is admin for this tenant
            if (!$this->isTenantAdmin()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized. Admin access required.'
                ], 403);
            }

            $user = User::where('tenant_id', $this->currentTenant->id)
                ->where(function ($q) use ($id) {
                    $q->where('id', $id)->orWhere('uuid', $id);
                })
                ->firstOrFail();

            // Prevent self-role-change unless super-admin
            $isSelfUpdate = $user->id === auth()->id();
            $isSuperAdmin = auth()->user()->hasRole('super-admin');

            if ($isSelfUpdate && !$isSuperAdmin) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot change your own role'
                ], 400);
            }

            // Validate request
            $validator = Validator::make($request->all(), [
                'role' => 'required|string|in:admin,staff,viewer',
                'reason' => 'nullable|string|max:500',
            ]);

            if ($validator->fails()) {
                throw new ValidationException($validator);
            }

            $newRole = $request->get('role');
            $reason = $request->get('reason', 'Role change by administrator');
            $oldRole = $user->roles->first()->name ?? 'none';

            // Find the role
            $roleModel = Role::where('name', $newRole)->first();
            if (!$roleModel) {
                throw new \Exception('Role not found');
            }

            // Update user role
            $user->roles()->sync([$roleModel->id]);

            // Add to metadata
            $metadata = $user->metadata ?? [];
            $metadata['role_changes'][] = [
                'date' => now()->toISOString(),
                'old_role' => $oldRole,
                'new_role' => $newRole,
                'reason' => $reason,
                'changed_by' => auth()->id(),
            ];
            $user->update(['metadata' => $metadata]);

            Log::channel('user')->info('User role changed', [
                'user_id' => $user->id,
                'email' => $user->email,
                'old_role' => $oldRole,
                'new_role' => $newRole,
                'reason' => $reason,
                'tenant_id' => $this->currentTenant->id,
                'changed_by' => auth()->id(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'User role changed successfully',
                'data' => [
                    'id' => $user->id,
                    'email' => $user->email,
                    'old_role' => $oldRole,
                    'new_role' => $newRole,
                    'changed_at' => now()->toISOString(),
                    'reason' => $reason,
                ]
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'User not found'
            ], 404);

        } catch (\Exception $e) {
            Log::channel('user')->error('Failed to change user role', [
                'user_id' => $id,
                'tenant_id' => $this->currentTenant?->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to change user role',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Check if current user is a tenant admin
     */
    private function isTenantAdmin(): bool
    {
        if (!$this->currentTenant) {
            return false;
        }

        // Check if user is authenticated
        if (!auth()->check()) {
            return false;
        }

        $user = auth()->user();

        // Check if user belongs to this tenant
        if ($user->tenant_id !== $this->currentTenant->id) {
            return false;
        }

        // Check if user has admin role for this tenant
        return $user->hasRole('admin') || $user->hasRole('super-admin');
    }

    /**
     * Check if user has critical data before deletion
     */
    private function checkUserCriticalData(User $user): bool
    {
        // Check if user has created any payments, customers, vouchers, etc.
        $hasCreatedPayments = \App\Models\Payment::where('created_by', $user->id)->exists();
        $hasCreatedCustomers = \App\Models\Customer::where('created_by', $user->id)->exists();
        $hasCreatedVouchers = \App\Models\Voucher::where('created_by', $user->id)->exists();

        // Check if user is the last admin
        if ($user->hasRole('admin') || $user->hasRole('super-admin')) {
            $adminCount = User::where('tenant_id', $this->currentTenant->id)
                ->whereHas('roles', function ($q) {
                    $q->whereIn('name', ['admin', 'super-admin']);
                })
                ->where('id', '!=', $user->id)
                ->where('is_active', true)
                ->count();

            if ($adminCount === 0) {
                return true; // Cannot delete last admin
            }
        }

        return $hasCreatedPayments || $hasCreatedCustomers || $hasCreatedVouchers;
    }

    /**
     * Format user response
     */
    private function formatUserResponse(User $user, bool $detailed = false): array
    {
        $response = [
            'id' => $user->id,
            'uuid' => $user->uuid,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'status' => $user->status,
            'is_active' => $user->is_active,
            'roles' => $user->roles->pluck('name')->toArray(),
            'created_at' => $user->created_at->toISOString(),
            'updated_at' => $user->updated_at->toISOString(),
            'last_login_at' => $user->last_login_at?->toISOString(),
        ];

        if ($detailed) {
            $response['permissions'] = $user->getAllPermissions()->pluck('name')->toArray();
            $response['email_verified_at'] = $user->email_verified_at?->toISOString();
            $response['suspended_at'] = $user->suspended_at?->toISOString();
            $response['suspension_end_at'] = $user->suspension_end_at?->toISOString();
            $response['suspension_reason'] = $user->suspension_reason;
            $response['activated_at'] = $user->activated_at?->toISOString();
            $response['metadata'] = $user->metadata ?? [];
        }

        return $response;
    }
}
