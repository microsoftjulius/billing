<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Services\DatabaseContextService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CustomerController extends Controller
{
    /**
     * Get customers list with tenant isolation
     */
    public function index(Request $request)
    {
        try {
            $user = auth()->user();
            $connection = DatabaseContextService::getConnection($user);
            
            // Build query with proper tenant filtering
            $query = DB::connection($connection)->table('customers');
            
            // Apply tenant filtering for tenant users
            if (!DatabaseContextService::isGlobalAdmin($user)) {
                $query->where('tenant_id', $user->tenant_id);
            }
            
            // Search functionality
            if ($request->has('search') && $request->search) {
                $searchTerm = '%' . $request->search . '%';
                $query->where(function($q) use ($searchTerm) {
                    $q->where('name', 'ILIKE', $searchTerm)
                      ->orWhere('email', 'ILIKE', $searchTerm)
                      ->orWhere('phone', 'ILIKE', $searchTerm);
                });
            }
            
            // Filter by status
            if ($request->has('status')) {
                $query->where('is_active', $request->status === 'active');
            }
            
            // Sort
            $sortBy = $request->get('sort_by', 'created_at');
            $sortOrder = $request->get('sort_order', 'desc');
            $query->orderBy($sortBy, $sortOrder);
            
            // Pagination
            $perPage = $request->get('per_page', 15);
            $page = $request->get('page', 1);
            $offset = ($page - 1) * $perPage;
            
            $total = $query->count();
            $customers = $query->offset($offset)->limit($perPage)->get();
            
            // Get additional data for each customer
            $customersWithStats = $customers->map(function ($customer) use ($connection) {
                // Get payment count
                $paymentCount = DB::connection($connection)
                    ->table('payments')
                    ->where('customer_id', $customer->id)
                    ->count();
                
                // Get voucher count
                $voucherCount = DB::connection($connection)
                    ->table('vouchers')
                    ->where('customer_id', $customer->id)
                    ->count();
                
                // Get active vouchers count
                $activeVouchers = DB::connection($connection)
                    ->table('vouchers')
                    ->where('customer_id', $customer->id)
                    ->where('status', 'active')
                    ->count();
                
                // Get total spent
                $totalSpent = DB::connection($connection)
                    ->table('payments')
                    ->where('customer_id', $customer->id)
                    ->where('status', 'completed')
                    ->sum('amount');
                
                return [
                    'id' => $customer->id,
                    'uuid' => $customer->uuid,
                    'name' => $customer->name,
                    'email' => $customer->email,
                    'phone' => $customer->phone,
                    'address' => $customer->address,
                    'id_type' => $customer->id_type,
                    'id_number' => $customer->id_number,
                    'date_of_birth' => $customer->date_of_birth,
                    'gender' => $customer->gender,
                    'is_active' => $customer->is_active,
                    'last_login_at' => $customer->last_login_at,
                    'tenant_id' => $customer->tenant_id,
                    'created_at' => $customer->created_at,
                    'updated_at' => $customer->updated_at,
                    'stats' => [
                        'total_payments' => $paymentCount,
                        'total_vouchers' => $voucherCount,
                        'active_vouchers' => $activeVouchers,
                        'total_spent' => (float) $totalSpent,
                    ]
                ];
            });
            
            return response()->json([
                'success' => true,
                'data' => $customersWithStats,
                'pagination' => [
                    'current_page' => $page,
                    'last_page' => ceil($total / $perPage),
                    'per_page' => $perPage,
                    'total' => $total,
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('Customer index error', [
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
                'tenant_id' => auth()->user()->tenant_id ?? null
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to load customers',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Create a new customer
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'nullable|email',
            'phone' => 'required|string|max:20',
            'address' => 'nullable|string|max:500',
            'id_type' => 'nullable|string|in:national_id,passport,driving_license',
            'id_number' => 'nullable|string|max:50',
            'date_of_birth' => 'nullable|date|before:today',
            'gender' => 'nullable|string|in:male,female,other',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $user = auth()->user();
            
            // Only tenant users can create customers
            if (DatabaseContextService::isGlobalAdmin($user)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Global admins cannot create customers directly'
                ], 403);
            }
            
            $connection = DatabaseContextService::getConnection($user);
            
            // Check for duplicate email/phone within tenant
            if ($request->email) {
                $existingEmail = DB::connection($connection)
                    ->table('customers')
                    ->where('email', $request->email)
                    ->where('tenant_id', $user->tenant_id)
                    ->exists();
                
                if ($existingEmail) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Email already exists for this tenant',
                        'errors' => ['email' => ['Email already exists']]
                    ], 422);
                }
            }
            
            $existingPhone = DB::connection($connection)
                ->table('customers')
                ->where('phone', $request->phone)
                ->where('tenant_id', $user->tenant_id)
                ->exists();
            
            if ($existingPhone) {
                return response()->json([
                    'success' => false,
                    'message' => 'Phone number already exists for this tenant',
                    'errors' => ['phone' => ['Phone number already exists']]
                ], 422);
            }
            
            $customerId = \Illuminate\Support\Str::orderedUuid();
            
            $customerData = [
                'id' => $customerId,
                'uuid' => \Illuminate\Support\Str::orderedUuid(),
                'name' => $request->name,
                'email' => $request->email,
                'phone' => $request->phone,
                'address' => $request->address,
                'id_type' => $request->id_type,
                'id_number' => $request->id_number,
                'date_of_birth' => $request->date_of_birth,
                'gender' => $request->gender,
                'is_active' => true,
                'tenant_id' => $user->tenant_id,
                'metadata' => json_encode([
                    'created_by' => $user->id,
                    'registration_ip' => $request->ip(),
                    'registration_source' => 'admin_panel'
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ];
            
            DB::connection($connection)->table('customers')->insert($customerData);
            
            // Get the created customer
            $customer = DB::connection($connection)
                ->table('customers')
                ->where('id', $customerId)
                ->first();

            return response()->json([
                'success' => true,
                'message' => 'Customer created successfully',
                'data' => $customer
            ], 201);

        } catch (\Exception $e) {
            Log::error('Customer creation error', [
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
                'tenant_id' => auth()->user()->tenant_id ?? null
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to create customer',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Get a specific customer
     */
    public function show($id)
    {
        try {
            $user = auth()->user();
            $connection = DatabaseContextService::getConnection($user);
            
            $query = DB::connection($connection)->table('customers')->where('id', $id);
            
            // Apply tenant filtering for tenant users
            if (!DatabaseContextService::isGlobalAdmin($user)) {
                $query->where('tenant_id', $user->tenant_id);
            }
            
            $customer = $query->first();
            
            if (!$customer) {
                return response()->json([
                    'success' => false,
                    'message' => 'Customer not found'
                ], 404);
            }
            
            // Get customer's payments
            $payments = DB::connection($connection)
                ->table('payments')
                ->where('customer_id', $customer->id)
                ->orderBy('created_at', 'desc')
                ->limit(10)
                ->get();
            
            // Get customer's vouchers
            $vouchers = DB::connection($connection)
                ->table('vouchers')
                ->where('customer_id', $customer->id)
                ->orderBy('created_at', 'desc')
                ->limit(10)
                ->get();
            
            $customerData = [
                'id' => $customer->id,
                'uuid' => $customer->uuid,
                'name' => $customer->name,
                'email' => $customer->email,
                'phone' => $customer->phone,
                'address' => $customer->address,
                'id_type' => $customer->id_type,
                'id_number' => $customer->id_number,
                'date_of_birth' => $customer->date_of_birth,
                'gender' => $customer->gender,
                'is_active' => $customer->is_active,
                'last_login_at' => $customer->last_login_at,
                'tenant_id' => $customer->tenant_id,
                'metadata' => $customer->metadata ? json_decode($customer->metadata, true) : null,
                'created_at' => $customer->created_at,
                'updated_at' => $customer->updated_at,
                'payments' => $payments,
                'vouchers' => $vouchers,
            ];

            return response()->json([
                'success' => true,
                'data' => $customerData
            ]);

        } catch (\Exception $e) {
            Log::error('Customer show error', [
                'error' => $e->getMessage(),
                'customer_id' => $id,
                'user_id' => auth()->id()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to load customer',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Update a customer
     */
    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'nullable|email',
            'phone' => 'required|string|max:20',
            'address' => 'nullable|string|max:500',
            'id_type' => 'nullable|string|in:national_id,passport,driving_license',
            'id_number' => 'nullable|string|max:50',
            'date_of_birth' => 'nullable|date|before:today',
            'gender' => 'nullable|string|in:male,female,other',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $user = auth()->user();
            $connection = DatabaseContextService::getConnection($user);
            
            // Check if customer exists and belongs to tenant
            $query = DB::connection($connection)->table('customers')->where('id', $id);
            
            if (!DatabaseContextService::isGlobalAdmin($user)) {
                $query->where('tenant_id', $user->tenant_id);
            }
            
            $customer = $query->first();
            
            if (!$customer) {
                return response()->json([
                    'success' => false,
                    'message' => 'Customer not found'
                ], 404);
            }
            
            // Check for duplicate email/phone within tenant (excluding current customer)
            if ($request->email && $request->email !== $customer->email) {
                $existingEmail = DB::connection($connection)
                    ->table('customers')
                    ->where('email', $request->email)
                    ->where('tenant_id', $customer->tenant_id)
                    ->where('id', '!=', $id)
                    ->exists();
                
                if ($existingEmail) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Email already exists for this tenant',
                        'errors' => ['email' => ['Email already exists']]
                    ], 422);
                }
            }
            
            if ($request->phone !== $customer->phone) {
                $existingPhone = DB::connection($connection)
                    ->table('customers')
                    ->where('phone', $request->phone)
                    ->where('tenant_id', $customer->tenant_id)
                    ->where('id', '!=', $id)
                    ->exists();
                
                if ($existingPhone) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Phone number already exists for this tenant',
                        'errors' => ['phone' => ['Phone number already exists']]
                    ], 422);
                }
            }
            
            $metadata = $customer->metadata ? json_decode($customer->metadata, true) : [];
            $metadata['updated_by'] = $user->id;
            $metadata['updated_at'] = now()->toISOString();
            
            $updateData = [
                'name' => $request->name,
                'email' => $request->email,
                'phone' => $request->phone,
                'address' => $request->address,
                'id_type' => $request->id_type,
                'id_number' => $request->id_number,
                'date_of_birth' => $request->date_of_birth,
                'gender' => $request->gender,
                'is_active' => $request->get('is_active', $customer->is_active),
                'metadata' => json_encode($metadata),
                'updated_at' => now(),
            ];
            
            DB::connection($connection)
                ->table('customers')
                ->where('id', $id)
                ->update($updateData);
            
            // Get updated customer
            $updatedCustomer = DB::connection($connection)
                ->table('customers')
                ->where('id', $id)
                ->first();

            return response()->json([
                'success' => true,
                'message' => 'Customer updated successfully',
                'data' => $updatedCustomer
            ]);

        } catch (\Exception $e) {
            Log::error('Customer update error', [
                'error' => $e->getMessage(),
                'customer_id' => $id,
                'user_id' => auth()->id()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to update customer',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Delete a customer
     */
    public function destroy($id)
    {
        try {
            $user = auth()->user();
            $connection = DatabaseContextService::getConnection($user);
            
            // Check if customer exists and belongs to tenant
            $query = DB::connection($connection)->table('customers')->where('id', $id);
            
            if (!DatabaseContextService::isGlobalAdmin($user)) {
                $query->where('tenant_id', $user->tenant_id);
            }
            
            $customer = $query->first();
            
            if (!$customer) {
                return response()->json([
                    'success' => false,
                    'message' => 'Customer not found'
                ], 404);
            }
            
            // Check if customer has active vouchers
            $activeVouchers = DB::connection($connection)
                ->table('vouchers')
                ->where('customer_id', $id)
                ->where('status', 'active')
                ->exists();
            
            if ($activeVouchers) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete customer with active vouchers'
                ], 400);
            }

            DB::connection($connection)->table('customers')->where('id', $id)->delete();

            return response()->json([
                'success' => true,
                'message' => 'Customer deleted successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Customer deletion error', [
                'error' => $e->getMessage(),
                'customer_id' => $id,
                'user_id' => auth()->id()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete customer',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Get customer statistics
     */
    public function stats()
    {
        try {
            $user = auth()->user();
            $connection = DatabaseContextService::getConnection($user);
            
            $query = DB::connection($connection)->table('customers');
            
            // Apply tenant filtering for tenant users
            if (!DatabaseContextService::isGlobalAdmin($user)) {
                $query->where('tenant_id', $user->tenant_id);
            }
            
            $stats = [
                'total' => (clone $query)->count(),
                'active' => (clone $query)->where('is_active', true)->count(),
                'inactive' => (clone $query)->where('is_active', false)->count(),
                'new_today' => (clone $query)->whereDate('created_at', today())->count(),
                'new_this_week' => (clone $query)->where('created_at', '>=', now()->startOfWeek())->count(),
                'new_this_month' => (clone $query)->where('created_at', '>=', now()->startOfMonth())->count(),
            ];
            
            // Get customers with active vouchers
            $customersWithActiveVouchers = DB::connection($connection)
                ->table('customers')
                ->join('vouchers', 'customers.id', '=', 'vouchers.customer_id')
                ->where('vouchers.status', 'active');
            
            if (!DatabaseContextService::isGlobalAdmin($user)) {
                $customersWithActiveVouchers->where('customers.tenant_id', $user->tenant_id);
            }
            
            $stats['with_active_vouchers'] = $customersWithActiveVouchers->distinct('customers.id')->count();

            return response()->json([
                'success' => true,
                'data' => $stats
            ]);

        } catch (\Exception $e) {
            Log::error('Customer stats error', [
                'error' => $e->getMessage(),
                'user_id' => auth()->id()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to get customer statistics',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }
}