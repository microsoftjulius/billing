<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Voucher;
use App\Models\Customer;
use App\Services\DatabaseContextService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class VoucherController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();
        $connection = DatabaseContextService::getConnection($user);
        
        // Build query with proper connection and tenant filtering
        $query = Voucher::on($connection)->with(['customer', 'payment']);
        
        // Apply tenant filtering
        if (DatabaseContextService::isGlobalAdmin($user)) {
            // Global admin can see all vouchers across tenants
            if ($request->has('tenant_id')) {
                $query->where('tenant_id', $request->tenant_id);
            }
        } else {
            // Tenant users only see their own vouchers
            $query->where('tenant_id', $user->tenant_id);
        }
        
        // Search functionality
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('code', 'ilike', "%{$search}%")
                  ->orWhereHas('customer', function($customerQuery) use ($search) {
                      $customerQuery->where('name', 'ilike', "%{$search}%")
                                   ->orWhere('phone', 'ilike', "%{$search}%");
                  });
            });
        }
        
        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }
        
        // Filter by profile
        if ($request->has('profile')) {
            $query->where('profile', $request->profile);
        }
        
        // Filter by date range
        if ($request->has('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        
        if ($request->has('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }
        
        // Sort
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);
        
        $vouchers = $query->paginate($request->get('per_page', 15));
        
        return response()->json([
            'success' => true,
            'data' => $vouchers->items(),
            'pagination' => [
                'current_page' => $vouchers->currentPage(),
                'last_page' => $vouchers->lastPage(),
                'per_page' => $vouchers->perPage(),
                'total' => $vouchers->total(),
            ]
        ]);
    }

    public function store(Request $request)
    {
        $user = auth()->user();
        $connection = DatabaseContextService::getConnection($user);
        
        $validator = Validator::make($request->all(), [
            'customer_id' => 'nullable|exists:customers,id',
            'profile' => 'required|string|max:100',
            'validity_hours' => 'required|integer|min:1|max:8760', // max 1 year
            'data_limit_mb' => 'nullable|integer|min:1',
            'price' => 'required|numeric|min:0',
            'currency' => 'required|string|size:3',
            'quantity' => 'integer|min:1|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $quantity = $request->get('quantity', 1);
            $vouchers = [];

            for ($i = 0; $i < $quantity; $i++) {
                $voucher = new Voucher();
                $voucher->setConnection($connection);
                $code = $voucher->generateCode();
                
                $voucherData = [
                    'customer_id' => $request->customer_id,
                    'code' => $code,
                    'password' => Str::random(8),
                    'profile' => $request->profile,
                    'validity_hours' => $request->validity_hours,
                    'data_limit_mb' => $request->data_limit_mb,
                    'price' => $request->price,
                    'currency' => $request->currency,
                    'status' => 'unused',
                    'tenant_id' => $user->tenant_id, // Add tenant context
                    'metadata' => [
                        'created_by' => $user->id,
                        'batch_id' => $quantity > 1 ? Str::uuid() : null,
                    ]
                ];

                $vouchers[] = Voucher::on($connection)->create($voucherData);
            }

            return response()->json([
                'success' => true,
                'message' => $quantity > 1 ? "{$quantity} vouchers created successfully" : "Voucher created successfully",
                'data' => $quantity === 1 ? $vouchers[0]->load(['customer', 'payment']) : $vouchers
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create voucher(s): ' . $e->getMessage()
            ], 500);
        }
    }

    public function show($identifier)
    {
        try {
            $user = auth()->user();
            $connection = DatabaseContextService::getConnection($user);
            
            $query = Voucher::on($connection)->with(['customer', 'payment']);
            
            // Apply tenant filtering
            if (!DatabaseContextService::isGlobalAdmin($user)) {
                $query->where('tenant_id', $user->tenant_id);
            }
            
            // Try to find by ID first, then by code
            if (is_numeric($identifier)) {
                $voucher = $query->findOrFail($identifier);
            } else {
                $voucher = $query->where('code', $identifier)->firstOrFail();
            }

            return response()->json([
                'success' => true,
                'data' => $voucher
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Voucher not found'
            ], 404);
        }
    }

    public function update(Request $request, $id)
    {
        $user = auth()->user();
        $connection = DatabaseContextService::getConnection($user);
        
        $validator = Validator::make($request->all(), [
            'customer_id' => 'nullable|exists:customers,id',
            'profile' => 'required|string|max:100',
            'validity_hours' => 'required|integer|min:1|max:8760',
            'data_limit_mb' => 'nullable|integer|min:1',
            'price' => 'required|numeric|min:0',
            'currency' => 'required|string|size:3',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $query = Voucher::on($connection);
            
            // Apply tenant filtering
            if (!DatabaseContextService::isGlobalAdmin($user)) {
                $query->where('tenant_id', $user->tenant_id);
            }
            
            $voucher = $query->findOrFail($id);
            
            // Don't allow editing of active or used vouchers
            if (in_array($voucher->status, ['active', 'used', 'expired'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot edit voucher with status: ' . $voucher->status
                ], 400);
            }
            
            $voucher->update([
                'customer_id' => $request->customer_id,
                'profile' => $request->profile,
                'validity_hours' => $request->validity_hours,
                'data_limit_mb' => $request->data_limit_mb,
                'price' => $request->price,
                'currency' => $request->currency,
                'metadata' => array_merge($voucher->metadata ?? [], [
                    'updated_by' => $user->id,
                    'updated_at' => now()->toISOString(),
                ])
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Voucher updated successfully',
                'data' => $voucher->load(['customer', 'payment'])
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update voucher: ' . $e->getMessage()
            ], 500);
        }
    }

    public function destroy($id)
    {
        try {
            $user = auth()->user();
            $connection = DatabaseContextService::getConnection($user);
            
            $query = Voucher::on($connection);
            
            // Apply tenant filtering
            if (!DatabaseContextService::isGlobalAdmin($user)) {
                $query->where('tenant_id', $user->tenant_id);
            }
            
            $voucher = $query->findOrFail($id);
            
            // Don't allow deletion of active vouchers
            if ($voucher->status === 'active') {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete active voucher'
                ], 400);
            }

            $voucher->delete();

            return response()->json([
                'success' => true,
                'message' => 'Voucher deleted successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete voucher: ' . $e->getMessage()
            ], 500);
        }
    }

    public function activate($id)
    {
        try {
            $user = auth()->user();
            $connection = DatabaseContextService::getConnection($user);
            
            $query = Voucher::on($connection);
            
            // Apply tenant filtering
            if (!DatabaseContextService::isGlobalAdmin($user)) {
                $query->where('tenant_id', $user->tenant_id);
            }
            
            $voucher = $query->findOrFail($id);
            
            if ($voucher->status !== 'unused') {
                return response()->json([
                    'success' => false,
                    'message' => 'Only unused vouchers can be activated'
                ], 400);
            }

            $voucher->activate();

            return response()->json([
                'success' => true,
                'message' => 'Voucher activated successfully',
                'data' => $voucher->fresh()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to activate voucher'
            ], 500);
        }
    }

    public function disable($identifier)
    {
        try {
            $user = auth()->user();
            $connection = DatabaseContextService::getConnection($user);
            
            $query = Voucher::on($connection);
            
            // Apply tenant filtering
            if (!DatabaseContextService::isGlobalAdmin($user)) {
                $query->where('tenant_id', $user->tenant_id);
            }
            
            // Try to find by ID first, then by code
            if (is_numeric($identifier)) {
                $voucher = $query->findOrFail($identifier);
            } else {
                $voucher = $query->where('code', $identifier)->firstOrFail();
            }
            
            $voucher->update(['status' => 'disabled']);

            return response()->json([
                'success' => true,
                'message' => 'Voucher disabled successfully',
                'data' => $voucher->fresh()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to disable voucher'
            ], 500);
        }
    }

    public function bulkGenerate(Request $request)
    {
        $user = auth()->user();
        $connection = DatabaseContextService::getConnection($user);
        
        $validator = Validator::make($request->all(), [
            'profile' => 'required|string|max:100',
            'validity_hours' => 'required|integer|min:1|max:8760',
            'data_limit_mb' => 'nullable|integer|min:1',
            'price' => 'required|numeric|min:0',
            'currency' => 'required|string|size:3',
            'quantity' => 'required|integer|min:1|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $quantity = $request->quantity;
            $batchId = Str::uuid();
            $vouchers = [];

            for ($i = 0; $i < $quantity; $i++) {
                $voucher = new Voucher();
                $voucher->setConnection($connection);
                $code = $voucher->generateCode();
                
                $voucherData = [
                    'code' => $code,
                    'password' => Str::random(8),
                    'profile' => $request->profile,
                    'validity_hours' => $request->validity_hours,
                    'data_limit_mb' => $request->data_limit_mb,
                    'price' => $request->price,
                    'currency' => $request->currency,
                    'status' => 'unused',
                    'tenant_id' => $user->tenant_id, // Add tenant context
                    'metadata' => [
                        'created_by' => $user->id,
                        'batch_id' => $batchId,
                        'bulk_generated' => true,
                    ]
                ];

                $vouchers[] = Voucher::on($connection)->create($voucherData);
            }

            return response()->json([
                'success' => true,
                'message' => "{$quantity} vouchers generated successfully",
                'data' => [
                    'batch_id' => $batchId,
                    'quantity' => $quantity,
                    'vouchers' => $vouchers
                ]
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate vouchers: ' . $e->getMessage()
            ], 500);
        }
    }

    public function statistics()
    {
        try {
            $user = auth()->user();
            $connection = DatabaseContextService::getConnection($user);
            
            $query = Voucher::on($connection);
            
            // Apply tenant filtering
            if (!DatabaseContextService::isGlobalAdmin($user)) {
                $query->where('tenant_id', $user->tenant_id);
            }
            
            $stats = [
                'active_vouchers' => (clone $query)->where('status', 'active')->count(),
                'expired_vouchers' => (clone $query)->where('status', 'expired')->count(),
                'total_revenue' => (clone $query)->sum('price'),
                'today_vouchers' => (clone $query)->whereDate('created_at', today())->count(),
                'total_vouchers' => (clone $query)->count(),
                'unused_vouchers' => (clone $query)->where('status', 'unused')->count(),
                'used_vouchers' => (clone $query)->where('status', 'used')->count(),
                'disabled_vouchers' => (clone $query)->where('status', 'disabled')->count(),
            ];

            return response()->json([
                'success' => true,
                'data' => $stats
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get voucher statistics'
            ], 500);
        }
    }

    public function stats()
    {
        try {
            $user = auth()->user();
            $connection = DatabaseContextService::getConnection($user);
            
            $query = Voucher::on($connection);
            
            // Apply tenant filtering
            if (!DatabaseContextService::isGlobalAdmin($user)) {
                $query->where('tenant_id', $user->tenant_id);
            }
            
            $stats = [
                'total' => (clone $query)->count(),
                'unused' => (clone $query)->where('status', 'unused')->count(),
                'active' => (clone $query)->where('status', 'active')->count(),
                'expired' => (clone $query)->where('status', 'expired')->count(),
                'used' => (clone $query)->where('status', 'used')->count(),
                'disabled' => (clone $query)->where('status', 'disabled')->count(),
                'created_today' => (clone $query)->whereDate('created_at', today())->count(),
                'created_this_week' => (clone $query)->where('created_at', '>=', now()->startOfWeek())->count(),
                'created_this_month' => (clone $query)->where('created_at', '>=', now()->startOfMonth())->count(),
                'expiring_soon' => (clone $query)->where('expires_at', '<=', now()->addHours(24))->count(),
                'total_value' => (clone $query)->sum('price'),
            ];

            return response()->json([
                'success' => true,
                'data' => $stats
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get voucher stats'
            ], 500);
        }
    }

    public function batchGenerate(Request $request)
    {
        return $this->bulkGenerate($request);
    }

    public function generateAdvanced(Request $request)
    {
        $user = auth()->user();
        $connection = DatabaseContextService::getConnection($user);
        
        $validator = Validator::make($request->all(), [
            'profile' => 'required|string|max:100',
            'validity_hours' => 'required|integer|min:1|max:8760',
            'data_limit_mb' => 'nullable|integer|min:1',
            'price' => 'required|numeric|min:0',
            'currency' => 'required|string|size:3',
            'code_prefix' => 'nullable|string|max:10',
            'customer_name' => 'nullable|string|max:255',
            'customer_phone' => 'nullable|string|max:20',
            'customer_email' => 'nullable|email|max:255',
            'auto_activate' => 'boolean',
            'send_sms' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Create customer if provided
            $customerId = null;
            if ($request->customer_name || $request->customer_phone || $request->customer_email) {
                $customer = Customer::on($connection)->create([
                    'name' => $request->customer_name,
                    'phone' => $request->customer_phone,
                    'email' => $request->customer_email,
                    'tenant_id' => $user->tenant_id,
                ]);
                $customerId = $customer->id;
            }

            $voucher = new Voucher();
            $voucher->setConnection($connection);
            $code = $request->code_prefix ? 
                $request->code_prefix . '-' . $voucher->generateCode() : 
                $voucher->generateCode();
            
            $voucherData = [
                'customer_id' => $customerId,
                'code' => $code,
                'password' => Str::random(8),
                'profile' => $request->profile,
                'validity_hours' => $request->validity_hours,
                'data_limit_mb' => $request->data_limit_mb,
                'price' => $request->price,
                'currency' => $request->currency,
                'status' => $request->auto_activate ? 'active' : 'unused',
                'tenant_id' => $user->tenant_id,
                'metadata' => [
                    'created_by' => $user->id,
                    'advanced_generated' => true,
                ]
            ];

            if ($request->auto_activate) {
                $voucherData['activated_at'] = now();
                $voucherData['expires_at'] = now()->addHours($request->validity_hours);
            }

            $voucher = Voucher::on($connection)->create($voucherData);

            return response()->json([
                'success' => true,
                'message' => 'Advanced voucher generated successfully',
                'data' => $voucher->load(['customer', 'payment'])
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate advanced voucher: ' . $e->getMessage()
            ], 500);
        }
    }

    public function resendSms($code)
    {
        try {
            $user = auth()->user();
            $connection = DatabaseContextService::getConnection($user);
            
            $query = Voucher::on($connection)->with('customer');
            
            // Apply tenant filtering
            if (!DatabaseContextService::isGlobalAdmin($user)) {
                $query->where('tenant_id', $user->tenant_id);
            }
            
            $voucher = $query->where('code', $code)->firstOrFail();
            
            if (!$voucher->customer || !$voucher->customer->phone) {
                return response()->json([
                    'success' => false,
                    'message' => 'Voucher has no customer or phone number'
                ], 400);
            }

            // Here you would integrate with your SMS service
            // For now, just mark as SMS sent
            $voucher->update([
                'sms_sent_at' => now(),
                'metadata' => array_merge($voucher->metadata ?? [], [
                    'sms_resent_by' => $user->id,
                    'sms_resent_at' => now()->toISOString(),
                ])
            ]);

            return response()->json([
                'success' => true,
                'message' => 'SMS sent successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to send SMS'
            ], 500);
        }
    }

    public function transfer($code, Request $request)
    {
        $validator = Validator::make($request->all(), [
            'new_customer_id' => 'required|exists:customers,id',
            'reason' => 'required|string|max:500',
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
            
            $query = Voucher::on($connection);
            
            // Apply tenant filtering
            if (!DatabaseContextService::isGlobalAdmin($user)) {
                $query->where('tenant_id', $user->tenant_id);
            }
            
            $voucher = $query->where('code', $code)->firstOrFail();
            
            $oldCustomerId = $voucher->customer_id;
            
            $voucher->update([
                'customer_id' => $request->new_customer_id,
                'metadata' => array_merge($voucher->metadata ?? [], [
                    'transferred_by' => $user->id,
                    'transferred_at' => now()->toISOString(),
                    'transfer_reason' => $request->reason,
                    'old_customer_id' => $oldCustomerId,
                ])
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Voucher transferred successfully',
                'data' => $voucher->fresh()->load(['customer', 'payment'])
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to transfer voucher'
            ], 500);
        }
    }

    public function refund($code, Request $request)
    {
        $validator = Validator::make($request->all(), [
            'refund_amount' => 'required|numeric|min:0',
            'reason' => 'required|string|max:500',
            'method' => 'required|in:manual,automatic',
            'allow_expired_refund' => 'boolean',
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
            
            $query = Voucher::on($connection);
            
            // Apply tenant filtering
            if (!DatabaseContextService::isGlobalAdmin($user)) {
                $query->where('tenant_id', $user->tenant_id);
            }
            
            $voucher = $query->where('code', $code)->firstOrFail();
            
            // Check if refund is allowed
            if ($voucher->status === 'expired' && !$request->allow_expired_refund) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot refund expired voucher'
                ], 400);
            }

            if ($request->refund_amount > $voucher->price) {
                return response()->json([
                    'success' => false,
                    'message' => 'Refund amount cannot exceed voucher price'
                ], 400);
            }

            $voucher->update([
                'status' => 'refunded',
                'metadata' => array_merge($voucher->metadata ?? [], [
                    'refunded_by' => $user->id,
                    'refunded_at' => now()->toISOString(),
                    'refund_amount' => $request->refund_amount,
                    'refund_reason' => $request->reason,
                    'refund_method' => $request->method,
                ])
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Voucher refunded successfully',
                'data' => $voucher->fresh()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to refund voucher'
            ], 500);
        }
    }

    public function analytics(Request $request)
    {
        try {
            $user = auth()->user();
            $connection = DatabaseContextService::getConnection($user);
            
            $query = Voucher::on($connection)->with(['customer', 'payment']);
            
            // Apply tenant filtering
            if (!DatabaseContextService::isGlobalAdmin($user)) {
                $query->where('tenant_id', $user->tenant_id);
            }
            
            // Apply date filters if provided
            if ($request->has('start_date')) {
                $query->whereDate('created_at', '>=', $request->start_date);
            }
            
            if ($request->has('end_date')) {
                $query->whereDate('created_at', '<=', $request->end_date);
            }
            
            // Get overview statistics
            $overview = [
                'total_vouchers' => (clone $query)->count(),
                'active_vouchers' => (clone $query)->where('status', 'active')->count(),
                'expired_vouchers' => (clone $query)->where('status', 'expired')->count(),
                'total_revenue' => (clone $query)->sum('price'),
            ];
            
            // Get profile breakdown
            $profileBreakdown = (clone $query)
                ->selectRaw('profile, COUNT(*) as count, SUM(price) as revenue')
                ->groupBy('profile')
                ->get()
                ->map(function ($item) {
                    return [
                        'profile' => $item->profile,
                        'count' => $item->count,
                        'revenue' => $item->revenue,
                    ];
                });
            
            // Get customer insights
            $customerInsights = [
                'vouchers_with_customers' => (clone $query)->whereNotNull('customer_id')->count(),
                'vouchers_without_customers' => (clone $query)->whereNull('customer_id')->count(),
            ];
            
            return response()->json([
                'success' => true,
                'data' => [
                    'overview' => $overview,
                    'profile_breakdown' => $profileBreakdown,
                    'customer_insights' => $customerInsights,
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get voucher analytics'
            ], 500);
        }
    }

    public function profiles()
    {
        $profiles = [
            'daily_1gb' => [
                'name' => 'Daily 1GB',
                'validity_hours' => 24,
                'data_limit_mb' => 1024,
                'suggested_price' => 1000,
            ],
            'daily_2gb' => [
                'name' => 'Daily 2GB',
                'validity_hours' => 24,
                'data_limit_mb' => 2048,
                'suggested_price' => 1500,
            ],
            'weekly_5gb' => [
                'name' => 'Weekly 5GB',
                'validity_hours' => 168,
                'data_limit_mb' => 5120,
                'suggested_price' => 5000,
            ],
            'weekly_10gb' => [
                'name' => 'Weekly 10GB',
                'validity_hours' => 168,
                'data_limit_mb' => 10240,
                'suggested_price' => 8000,
            ],
            'monthly_20gb' => [
                'name' => 'Monthly 20GB',
                'validity_hours' => 720,
                'data_limit_mb' => 20480,
                'suggested_price' => 15000,
            ],
            'monthly_50gb' => [
                'name' => 'Monthly 50GB',
                'validity_hours' => 720,
                'data_limit_mb' => 51200,
                'suggested_price' => 30000,
            ],
            'unlimited_daily' => [
                'name' => 'Unlimited Daily',
                'validity_hours' => 24,
                'data_limit_mb' => null,
                'suggested_price' => 2000,
            ],
            'unlimited_weekly' => [
                'name' => 'Unlimited Weekly',
                'validity_hours' => 168,
                'data_limit_mb' => null,
                'suggested_price' => 10000,
            ],
        ];

        return response()->json([
            'success' => true,
            'data' => $profiles
        ]);
    }
}