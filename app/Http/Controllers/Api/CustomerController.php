<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Tenant;
use App\Models\Payment;
use App\Models\Voucher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\DB;

class CustomerController extends Controller
{
    private ?Tenant $currentTenant;

    public function __construct()
    {
        $this->currentTenant = $this->resolveTenant();
    }

    /**
     * Search customers by name, phone, or email
     */
    public function search(Request $request, string $query): JsonResponse
    {
        try {
            $limit = min($request->get('limit', 20), 50);
            $includeDetails = $request->get('include_details', false);

            $searchQuery = Customer::query()
                ->withCount(['payments', 'vouchers'])
                ->withSum('payments', 'amount');

            // Scope by tenant if current tenant exists
            if ($this->currentTenant) {
                $searchQuery->where('tenant_id', $this->currentTenant->id);
            }

            // Build search conditions
            $searchQuery->where(function ($q) use ($query) {
                // Search by name
                $q->where('name', 'LIKE', '%' . $query . '%')
                    // Search by phone (exact match or partial)
                    ->orWhere('phone', 'LIKE', '%' . $query . '%')
                    // Search by email
                    ->orWhere('email', 'LIKE', '%' . $query . '%')
                    // Search by customer ID or UUID
                    ->orWhere('id', $query)
                    ->orWhere('uuid', $query);
            });

            // Apply additional filters if provided
            if ($request->has('is_active')) {
                $searchQuery->where('is_active', $request->get('is_active') === 'true');
            }

            if ($request->has('has_payments')) {
                if ($request->get('has_payments') === 'true') {
                    $searchQuery->has('payments');
                } else {
                    $searchQuery->doesntHave('payments');
                }
            }

            if ($request->has('has_vouchers')) {
                if ($request->get('has_vouchers') === 'true') {
                    $searchQuery->has('vouchers');
                } else {
                    $searchQuery->doesntHave('vouchers');
                }
            }

            if ($request->has('min_spent')) {
                $searchQuery->havingRaw('COALESCE(payments_sum_amount, 0) >= ?', [$request->get('min_spent')]);
            }

            if ($request->has('max_spent')) {
                $searchQuery->havingRaw('COALESCE(payments_sum_amount, 0) <= ?', [$request->get('max_spent')]);
            }

            // Order by relevance (exact matches first, then partial matches)
            $searchQuery->orderByRaw("
            CASE
                WHEN phone = ? THEN 1
                WHEN name = ? THEN 2
                WHEN email = ? THEN 3
                WHEN phone LIKE ? THEN 4
                WHEN name LIKE ? THEN 5
                WHEN email LIKE ? THEN 6
                ELSE 7
            END
        ", [
                $query, // phone = query
                $query, // name = query
                $query, // email = query
                $query . '%', // phone starts with query
                $query . '%', // name starts with query
                $query . '%', // email starts with query
            ])->orderBy('created_at', 'desc');

            $customers = $searchQuery->limit($limit)->get();

            // Get search statistics
            $totalMatches = $searchQuery->count();
            $exactPhoneMatches = Customer::where('tenant_id', $this->currentTenant->id)
                ->where('phone', $query)
                ->count();
            $exactEmailMatches = Customer::where('tenant_id', $this->currentTenant->id)
                ->where('email', $query)
                ->count();

            $response = [
                'success' => true,
                'data' => [
                    'query' => $query,
                    'results' => $customers->map(function ($customer) use ($includeDetails) {
                        $result = [
                            'id' => $customer->id,
                            'uuid' => $customer->uuid,
                            'name' => $customer->name,
                            'phone' => $customer->phone,
                            'email' => $customer->email,
                            'is_active' => $customer->is_active,
                            'payments_count' => $customer->payments_count ?? 0,
                            'vouchers_count' => $customer->vouchers_count ?? 0,
                            'total_spent' => $customer->payments_sum_amount ?? 0,
                            'last_payment' => $customer->payments()->latest()->first()?->created_at?->toISOString(),
                            'created_at' => $customer->created_at->toISOString(),
                        ];

                        if ($includeDetails) {
                            $result['avg_payment_amount'] = $customer->payments_count > 0
                                ? round(($customer->payments_sum_amount ?? 0) / $customer->payments_count, 2)
                                : 0;
                            $result['preferred_package'] = $this->getPreferredPackage($customer);
                            $result['customer_since'] = $customer->payments()->oldest()->first()?->created_at?->toISOString()
                                ?? $customer->created_at->toISOString();
                        }

                        return $result;
                    })->toArray(),
                    'statistics' => [
                        'total_matches' => $totalMatches,
                        'returned_results' => $customers->count(),
                        'exact_phone_matches' => $exactPhoneMatches,
                        'exact_email_matches' => $exactEmailMatches,
                        'search_limit' => $limit,
                        'has_more_results' => $totalMatches > $limit,
                    ],
                    'suggestions' => $this->getSearchSuggestions($query, $customers),
                    'search_time' => now()->toISOString(),
                ]
            ];

            // Add tenant info to response if tenant exists
            if ($this->currentTenant) {
                $response['tenant'] = [
                    'id' => $this->currentTenant->id,
                    'code' => $this->currentTenant->code,
                    'name' => $this->currentTenant->name,
                ];
            }

            // Log search activity
            Log::channel('customer')->info('Customer search performed', [
                'tenant_id' => $this->currentTenant?->id,
                'query' => $query,
                'total_matches' => $totalMatches,
                'results_returned' => $customers->count(),
                'searched_by' => auth()->id() ?? 'system',
            ]);

            return response()->json($response);

        } catch (\Exception $e) {
            Log::channel('customer')->error('Failed to search customers', [
                'tenant_id' => $this->currentTenant?->id,
                'query' => $query,
                'error' => $e->getMessage(),
                'filters' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to search customers',
                'error' => config('app.debug') ? $e->getMessage() : null,
                'data' => [
                    'query' => $query,
                    'search_time' => now()->toISOString(),
                ]
            ], 500);
        }
    }

    /**
     * Get search suggestions based on query
     */
    private function getSearchSuggestions(string $query, $results): array
    {
        $suggestions = [];

        // If no results found, suggest similar phone numbers
        if ($results->isEmpty()) {
            // Check for phone numbers with similar patterns
            $similarPhones = Customer::where('tenant_id', $this->currentTenant->id)
                ->where('phone', 'LIKE', '%' . substr($query, -4) . '%') // Last 4 digits
                ->limit(5)
                ->pluck('phone')
                ->toArray();

            if (!empty($similarPhones)) {
                $suggestions[] = [
                    'type' => 'similar_phones',
                    'message' => 'Try these similar phone numbers:',
                    'data' => $similarPhones,
                ];
            }

            // Check if query looks like a phone number
            if (preg_match('/^[0-9]{10}$/', $query)) {
                $formattedPhone = '256' . substr($query, 1); // Convert to 256 format
                $suggestions[] = [
                    'type' => 'phone_format',
                    'message' => 'Try searching with international format:',
                    'data' => [$formattedPhone],
                ];
            }
        }

        // If results found but less than 3, suggest expanding search
        if ($results->count() > 0 && $results->count() < 3) {
            $suggestions[] = [
                'type' => 'expand_search',
                'message' => 'Try expanding your search with fewer characters',
                'data' => ['Try searching with: ' . substr($query, 0, -1)],
            ];
        }

        // If many results found, suggest filtering
        if ($results->count() > 10) {
            $suggestions[] = [
                'type' => 'filter_results',
                'message' => 'Too many results. Try adding more specific search terms or use filters.',
                'data' => ['Use filters: has_payments=true', 'min_spent=10000'],
            ];
        }

        return $suggestions;
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
     * Display a listing of customers
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $perPage = min($request->get('per_page', 15), 100);
            $page = $request->get('page', 1);

            $query = Customer::query()
                ->withCount(['payments', 'vouchers'])
                ->withSum('payments', 'amount')
                ->orderBy('created_at', 'desc');

            // Scope by tenant if current tenant exists
            if ($this->currentTenant) {
                $query->where('tenant_id', $this->currentTenant->id);
            }

            // Apply filters
            if ($request->has('name')) {
                $query->where('name', 'LIKE', '%' . $request->get('name') . '%');
            }

            if ($request->has('phone')) {
                $query->where('phone', 'LIKE', '%' . $request->get('phone') . '%');
            }

            if ($request->has('email')) {
                $query->where('email', 'LIKE', '%' . $request->get('email') . '%');
            }

            if ($request->has('is_active')) {
                $query->where('is_active', $request->get('is_active') === 'true');
            }

            if ($request->has('registration_source')) {
                $query->whereJsonContains('metadata->registration_source', $request->get('registration_source'));
            }

            if ($request->has('start_date')) {
                $query->whereDate('created_at', '>=', $request->get('start_date'));
            }

            if ($request->has('end_date')) {
                $query->whereDate('created_at', '<=', $request->get('end_date'));
            }

            if ($request->has('min_payments')) {
                $query->has('payments', '>=', $request->get('min_payments'));
            }

            if ($request->has('max_payments')) {
                $query->has('payments', '<=', $request->get('max_payments'));
            }

            if ($request->has('min_spent')) {
                $query->havingRaw('COALESCE(payments_sum_amount, 0) >= ?', [$request->get('min_spent')]);
            }

            if ($request->has('max_spent')) {
                $query->havingRaw('COALESCE(payments_sum_amount, 0) <= ?', [$request->get('max_spent')]);
            }

            // Filter by tenant_id if explicitly requested (admin only)
            if ($request->has('tenant_id') && !$this->currentTenant && auth()->user()?->isAdmin()) {
                $query->where('tenant_id', $request->get('tenant_id'));
            }

            $customers = $query->paginate($perPage, ['*'], 'page', $page);

            $responseData = [
                'success' => true,
                'data' => [
                    'customers' => $customers->map(function ($customer) {
                        return $this->formatCustomerResponse($customer);
                    })->toArray(),
                    'pagination' => [
                        'total' => $customers->total(),
                        'per_page' => $customers->perPage(),
                        'current_page' => $customers->currentPage(),
                        'last_page' => $customers->lastPage(),
                        'from' => $customers->firstItem(),
                        'to' => $customers->lastItem(),
                    ],
                    'summary' => [
                        'total_customers' => $customers->total(),
                        'active_customers' => $customers->where('is_active', true)->count(),
                        'total_revenue' => $customers->sum('payments_sum_amount') ?? 0,
                        'avg_revenue_per_customer' => $customers->count() > 0
                            ? round(($customers->sum('payments_sum_amount') ?? 0) / $customers->count(), 2)
                            : 0,
                        'avg_payments_per_customer' => $customers->count() > 0
                            ? round($customers->sum('payments_count') / $customers->count(), 2)
                            : 0,
                    ]
                ]
            ];

            // Add tenant info to response if tenant exists
            if ($this->currentTenant) {
                $responseData['tenant'] = [
                    'id' => $this->currentTenant->id,
                    'code' => $this->currentTenant->code,
                    'name' => $this->currentTenant->name,
                ];
            }

            return response()->json($responseData);

        } catch (\Exception $e) {
            Log::channel('customer')->error('Failed to fetch customers', [
                'tenant_id' => $this->currentTenant?->id,
                'error' => $e->getMessage(),
                'filters' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch customers',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Store a newly created customer
     */
    public function store(Request $request): JsonResponse
    {
        try {
            // Validate request
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'phone' => 'required|string|max:20|unique:customers,phone' . ($this->currentTenant ? ',NULL,id,tenant_id,' . $this->currentTenant->id : ''),
                'email' => 'nullable|email|max:255',
                'is_active' => 'boolean',
                'metadata' => 'nullable|array',
            ]);

            if ($validator->fails()) {
                throw new ValidationException($validator);
            }

            $customerData = $request->only(['name', 'phone', 'email', 'is_active', 'metadata']);
            $customerData['is_active'] = $customerData['is_active'] ?? true;
            $customerData['metadata'] = array_merge($customerData['metadata'] ?? [], [
                'registration_source' => 'api',
                'registered_at' => now()->toISOString(),
                'registered_by' => auth()->id() ?? 'system',
            ]);

            // Add tenant_id if tenant exists
            if ($this->currentTenant) {
                $customerData['tenant_id'] = $this->currentTenant->id;
                $customerData['metadata']['tenant_id'] = $this->currentTenant->id;
                $customerData['metadata']['tenant_code'] = $this->currentTenant->code;
            }

            // Generate UUID
            $customerData['uuid'] = \Illuminate\Support\Str::orderedUuid();

            $customer = Customer::create($customerData);

            Log::channel('customer')->info('Customer created successfully', [
                'customer_id' => $customer->id,
                'phone' => $customer->phone,
                'tenant_id' => $this->currentTenant?->id,
                'created_by' => auth()->id() ?? 'system',
            ]);

            $response = [
                'success' => true,
                'message' => 'Customer created successfully',
                'data' => $this->formatCustomerResponse($customer)
            ];

            // Add tenant info to response if tenant exists
            if ($this->currentTenant) {
                $response['tenant'] = [
                    'id' => $this->currentTenant->id,
                    'code' => $this->currentTenant->code,
                    'name' => $this->currentTenant->name,
                ];
            }

            return response()->json($response, 201);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);

        } catch (\Exception $e) {
            Log::channel('customer')->error('Failed to create customer', [
                'tenant_id' => $this->currentTenant?->id,
                'error' => $e->getMessage(),
                'data' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to create customer',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Display the specified customer
     */
    public function show(string $id): JsonResponse
    {
        try {
            $query = Customer::query()
                ->withCount(['payments', 'vouchers'])
                ->withSum('payments', 'amount');

            // Scope by tenant if current tenant exists
            if ($this->currentTenant) {
                $query->where('tenant_id', $this->currentTenant->id);
            }

            // Try to find by ID or UUID
            $customer = $query->where(function ($q) use ($id) {
                $q->where('id', $id)->orWhere('uuid', $id);
            })->firstOrFail();

            return response()->json([
                'success' => true,
                'data' => $this->formatCustomerResponse($customer, true)
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Customer not found'
            ], 404);

        } catch (\Exception $e) {
            Log::channel('customer')->error('Failed to fetch customer', [
                'customer_id' => $id,
                'tenant_id' => $this->currentTenant?->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch customer details',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Update the specified customer
     */
    public function update(Request $request, string $id): JsonResponse
    {
        try {
            $query = Customer::query();

            // Scope by tenant if current tenant exists
            if ($this->currentTenant) {
                $query->where('tenant_id', $this->currentTenant->id);
            }

            // Try to find by ID or UUID
            $customer = $query->where(function ($q) use ($id) {
                $q->where('id', $id)->orWhere('uuid', $id);
            })->firstOrFail();

            // Validate request
            $validator = Validator::make($request->all(), [
                'name' => 'sometimes|required|string|max:255',
                'phone' => 'sometimes|required|string|max:20|unique:customers,phone,' . $customer->id . ',id' . ($this->currentTenant ? ',tenant_id,' . $this->currentTenant->id : ''),
                'email' => 'nullable|email|max:255',
                'is_active' => 'boolean',
                'metadata' => 'nullable|array',
            ]);

            if ($validator->fails()) {
                throw new ValidationException($validator);
            }

            $updateData = $request->only(['name', 'phone', 'email', 'is_active']);

            // Handle metadata update
            if ($request->has('metadata')) {
                $currentMetadata = $customer->metadata ?? [];
                $updateData['metadata'] = array_merge($currentMetadata, $request->get('metadata'));
            }

            $customer->update($updateData);

            Log::channel('customer')->info('Customer updated successfully', [
                'customer_id' => $customer->id,
                'phone' => $customer->phone,
                'tenant_id' => $this->currentTenant?->id,
                'updated_by' => auth()->id() ?? 'system',
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Customer updated successfully',
                'data' => $this->formatCustomerResponse($customer, true)
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
                'message' => 'Customer not found'
            ], 404);

        } catch (\Exception $e) {
            Log::channel('customer')->error('Failed to update customer', [
                'customer_id' => $id,
                'tenant_id' => $this->currentTenant?->id,
                'error' => $e->getMessage(),
                'data' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update customer',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Remove the specified customer
     */
    public function destroy(string $id): JsonResponse
    {
        try {
            $query = Customer::query();

            // Scope by tenant if current tenant exists
            if ($this->currentTenant) {
                $query->where('tenant_id', $this->currentTenant->id);
            }

            // Try to find by ID or UUID
            $customer = $query->where(function ($q) use ($id) {
                $q->where('id', $id)->orWhere('uuid', $id);
            })->firstOrFail();

            // Check if customer has payments or vouchers
            $hasPayments = $customer->payments()->exists();
            $hasVouchers = $customer->vouchers()->exists();

            if ($hasPayments || $hasVouchers) {
                // Soft delete instead
                $customer->update(['is_active' => false]);

                Log::channel('customer')->info('Customer deactivated (has related records)', [
                    'customer_id' => $customer->id,
                    'has_payments' => $hasPayments,
                    'has_vouchers' => $hasVouchers,
                    'tenant_id' => $this->currentTenant?->id,
                    'deactivated_by' => auth()->id() ?? 'system',
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Customer deactivated (has related payments or vouchers)',
                    'data' => [
                        'id' => $customer->id,
                        'status' => 'deactivated',
                        'deactivated_at' => now()->toISOString(),
                    ]
                ]);
            }

            // Actually delete if no related records
            $customerId = $customer->id;
            $customerPhone = $customer->phone;
            $customer->delete();

            Log::channel('customer')->info('Customer deleted successfully', [
                'customer_id' => $customerId,
                'phone' => $customerPhone,
                'tenant_id' => $this->currentTenant?->id,
                'deleted_by' => auth()->id() ?? 'system',
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Customer deleted successfully',
                'data' => [
                    'id' => $customerId,
                    'status' => 'deleted',
                    'deleted_at' => now()->toISOString(),
                ]
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Customer not found'
            ], 404);

        } catch (\Exception $e) {
            Log::channel('customer')->error('Failed to delete customer', [
                'customer_id' => $id,
                'tenant_id' => $this->currentTenant?->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete customer',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Get customer payments
     */
    public function payments(Request $request, string $id): JsonResponse
    {
        try {
            $query = Customer::query();

            // Scope by tenant if current tenant exists
            if ($this->currentTenant) {
                $query->where('tenant_id', $this->currentTenant->id);
            }

            // Try to find by ID or UUID
            $customer = $query->where(function ($q) use ($id) {
                $q->where('id', $id)->orWhere('uuid', $id);
            })->firstOrFail();

            $perPage = min($request->get('per_page', 15), 100);
            $page = $request->get('page', 1);

            $paymentsQuery = $customer->payments()
                ->with(['voucher'])
                ->orderBy('created_at', 'desc');

            // Apply filters
            if ($request->has('status')) {
                $paymentsQuery->where('status', $request->get('status'));
            }

            if ($request->has('start_date')) {
                $paymentsQuery->whereDate('created_at', '>=', $request->get('start_date'));
            }

            if ($request->has('end_date')) {
                $paymentsQuery->whereDate('created_at', '<=', $request->get('end_date'));
            }

            if ($request->has('min_amount')) {
                $paymentsQuery->where('amount', '>=', $request->get('min_amount'));
            }

            if ($request->has('max_amount')) {
                $paymentsQuery->where('amount', '<=', $request->get('max_amount'));
            }

            $payments = $paymentsQuery->paginate($perPage, ['*'], 'page', $page);

            $response = [
                'success' => true,
                'customer' => [
                    'id' => $customer->id,
                    'name' => $customer->name,
                    'phone' => $customer->phone,
                    'email' => $customer->email,
                ],
                'data' => [
                    'payments' => $payments->items(),
                    'pagination' => [
                        'total' => $payments->total(),
                        'per_page' => $payments->perPage(),
                        'current_page' => $payments->currentPage(),
                        'last_page' => $payments->lastPage(),
                        'from' => $payments->firstItem(),
                        'to' => $payments->lastItem(),
                    ],
                    'summary' => [
                        'total_payments' => $payments->total(),
                        'total_amount' => $payments->sum('amount'),
                        'completed_payments' => $payments->where('status', 'completed')->count(),
                        'pending_payments' => $payments->where('status', 'pending')->count(),
                        'failed_payments' => $payments->where('status', 'failed')->count(),
                        'avg_payment_amount' => $payments->count() > 0
                            ? round($payments->sum('amount') / $payments->count(), 2)
                            : 0,
                    ]
                ]
            ];

            // Add tenant info to response if tenant exists
            if ($this->currentTenant) {
                $response['tenant'] = [
                    'id' => $this->currentTenant->id,
                    'code' => $this->currentTenant->code,
                    'name' => $this->currentTenant->name,
                ];
            }

            return response()->json($response);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Customer not found'
            ], 404);

        } catch (\Exception $e) {
            Log::channel('customer')->error('Failed to fetch customer payments', [
                'customer_id' => $id,
                'tenant_id' => $this->currentTenant?->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch customer payments',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Get customer vouchers
     */
    public function vouchers(Request $request, string $id): JsonResponse
    {
        try {
            $query = Customer::query();

            // Scope by tenant if current tenant exists
            if ($this->currentTenant) {
                $query->where('tenant_id', $this->currentTenant->id);
            }

            // Try to find by ID or UUID
            $customer = $query->where(function ($q) use ($id) {
                $q->where('id', $id)->orWhere('uuid', $id);
            })->firstOrFail();

            $perPage = min($request->get('per_page', 15), 100);
            $page = $request->get('page', 1);

            $vouchersQuery = $customer->vouchers()
                ->with(['payment'])
                ->orderBy('created_at', 'desc');

            // Apply filters
            if ($request->has('status')) {
                $vouchersQuery->where('status', $request->get('status'));
            }

            if ($request->has('profile')) {
                $vouchersQuery->where('profile', $request->get('profile'));
            }

            if ($request->has('active')) {
                if ($request->get('active') === 'true') {
                    $vouchersQuery->active();
                } elseif ($request->get('active') === 'false') {
                    $vouchersQuery->where(function ($q) {
                        $q->where('expires_at', '<=', now())
                            ->orWhere('status', 'disabled');
                    });
                }
            }

            if ($request->has('expired')) {
                if ($request->get('expired') === 'true') {
                    $vouchersQuery->expired();
                } elseif ($request->get('expired') === 'false') {
                    $vouchersQuery->where('expires_at', '>', now());
                }
            }

            if ($request->has('start_date')) {
                $vouchersQuery->whereDate('created_at', '>=', $request->get('start_date'));
            }

            if ($request->has('end_date')) {
                $vouchersQuery->whereDate('created_at', '<=', $request->get('end_date'));
            }

            $vouchers = $vouchersQuery->paginate($perPage, ['*'], 'page', $page);

            $response = [
                'success' => true,
                'customer' => [
                    'id' => $customer->id,
                    'name' => $customer->name,
                    'phone' => $customer->phone,
                    'email' => $customer->email,
                ],
                'data' => [
                    'vouchers' => $vouchers->items(),
                    'pagination' => [
                        'total' => $vouchers->total(),
                        'per_page' => $vouchers->perPage(),
                        'current_page' => $vouchers->currentPage(),
                        'last_page' => $vouchers->lastPage(),
                        'from' => $vouchers->firstItem(),
                        'to' => $vouchers->lastItem(),
                    ],
                    'summary' => [
                        'total_vouchers' => $vouchers->total(),
                        'active_vouchers' => $vouchers->where('status', 'active')->count(),
                        'expired_vouchers' => $vouchers->where('expires_at', '<', now())->count(),
                        'disabled_vouchers' => $vouchers->where('status', 'disabled')->count(),
                        'total_value' => $vouchers->sum('price'),
                        'avg_voucher_value' => $vouchers->count() > 0
                            ? round($vouchers->sum('price') / $vouchers->count(), 2)
                            : 0,
                    ]
                ]
            ];

            // Add tenant info to response if tenant exists
            if ($this->currentTenant) {
                $response['tenant'] = [
                    'id' => $this->currentTenant->id,
                    'code' => $this->currentTenant->code,
                    'name' => $this->currentTenant->name,
                ];
            }

            return response()->json($response);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Customer not found'
            ], 404);

        } catch (\Exception $e) {
            Log::channel('customer')->error('Failed to fetch customer vouchers', [
                'customer_id' => $id,
                'tenant_id' => $this->currentTenant?->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch customer vouchers',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Get customer activity
     */
    public function activity(Request $request, string $id): JsonResponse
    {
        try {
            $query = Customer::query();

            // Scope by tenant if current tenant exists
            if ($this->currentTenant) {
                $query->where('tenant_id', $this->currentTenant->id);
            }

            // Try to find by ID or UUID
            $customer = $query->where(function ($q) use ($id) {
                $q->where('id', $id)->orWhere('uuid', $id);
            })->firstOrFail();

            $limit = min($request->get('limit', 20), 50);

            // Get recent payments
            $recentPayments = $customer->payments()
                ->with(['voucher'])
                ->orderBy('created_at', 'desc')
                ->limit($limit)
                ->get();

            // Get recent vouchers
            $recentVouchers = $customer->vouchers()
                ->with(['payment'])
                ->orderBy('created_at', 'desc')
                ->limit($limit)
                ->get();

            // Combine and sort by date
            $activities = collect()
                ->merge($recentPayments->map(function ($payment) {
                    return [
                        'type' => 'payment',
                        'id' => $payment->id,
                        'transaction_id' => $payment->transaction_id,
                        'amount' => $payment->amount,
                        'currency' => $payment->currency,
                        'status' => $payment->status,
                        'created_at' => $payment->created_at->toISOString(),
                        'has_voucher' => $payment->voucher ? true : false,
                        'voucher_code' => $payment->voucher?->code,
                    ];
                }))
                ->merge($recentVouchers->map(function ($voucher) {
                    return [
                        'type' => 'voucher',
                        'id' => $voucher->id,
                        'code' => $voucher->code,
                        'profile' => $voucher->profile,
                        'status' => $voucher->status,
                        'validity_hours' => $voucher->validity_hours,
                        'expires_at' => $voucher->expires_at?->toISOString(),
                        'created_at' => $voucher->created_at->toISOString(),
                        'has_payment' => $voucher->payment ? true : false,
                        'payment_transaction_id' => $voucher->payment?->transaction_id,
                    ];
                }))
                ->sortByDesc('created_at')
                ->values()
                ->take($limit);

            // Customer statistics
            $totalPayments = $customer->payments()->count();
            $totalVouchers = $customer->vouchers()->count();
            $totalSpent = $customer->payments()->where('status', 'completed')->sum('amount') ?? 0;
            $firstPayment = $customer->payments()->orderBy('created_at')->first();
            $lastPayment = $customer->payments()->orderByDesc('created_at')->first();

            $response = [
                'success' => true,
                'customer' => [
                    'id' => $customer->id,
                    'name' => $customer->name,
                    'phone' => $customer->phone,
                    'email' => $customer->email,
                    'is_active' => $customer->is_active,
                    'created_at' => $customer->created_at->toISOString(),
                ],
                'data' => [
                    'activities' => $activities,
                    'statistics' => [
                        'total_payments' => $totalPayments,
                        'total_vouchers' => $totalVouchers,
                        'total_spent' => $totalSpent,
                        'customer_since' => $firstPayment?->created_at?->toISOString() ?? $customer->created_at->toISOString(),
                        'last_activity' => $lastPayment?->created_at?->toISOString() ?? $customer->updated_at->toISOString(),
                        'days_since_registration' => now()->diffInDays($customer->created_at),
                        'avg_time_between_payments' => $this->calculateAverageTimeBetweenPayments($customer),
                        'preferred_package' => $this->getPreferredPackage($customer),
                        'success_rate' => $totalPayments > 0
                            ? round(($customer->payments()->where('status', 'completed')->count() / $totalPayments) * 100, 2)
                            : 0,
                    ]
                ]
            ];

            // Add tenant info to response if tenant exists
            if ($this->currentTenant) {
                $response['tenant'] = [
                    'id' => $this->currentTenant->id,
                    'code' => $this->currentTenant->code,
                    'name' => $this->currentTenant->name,
                ];
            }

            return response()->json($response);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Customer not found'
            ], 404);

        } catch (\Exception $e) {
            Log::channel('customer')->error('Failed to fetch customer activity', [
                'customer_id' => $id,
                'tenant_id' => $this->currentTenant?->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch customer activity',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Get customer statistics
     */
    public function statistics(Request $request): JsonResponse
    {
        try {
            $period = $request->get('period', 'today'); // today, week, month, year, custom

            $query = Customer::query();

            // Scope by tenant if current tenant exists
            if ($this->currentTenant) {
                $query->where('tenant_id', $this->currentTenant->id);
            }

            // Apply period filter
            switch ($period) {
                case 'today':
                    $query->whereDate('created_at', today());
                    break;
                case 'week':
                    $query->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()]);
                    break;
                case 'month':
                    $query->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()]);
                    break;
                case 'year':
                    $query->whereBetween('created_at', [now()->startOfYear(), now()->endOfYear()]);
                    break;
                case 'custom':
                    if ($request->has('start_date') && $request->has('end_date')) {
                        $query->whereBetween('created_at', [$request->get('start_date'), $request->get('end_date')]);
                    }
                    break;
            }

            $totalCustomers = $query->count();
            $activeCustomers = (clone $query)->where('is_active', true)->count();
            $newCustomers = $totalCustomers; // For period filter, all are new in that period

            // Get customers with payments
            $customersWithPayments = (clone $query)->has('payments')->count();
            $customersWithVouchers = (clone $query)->has('vouchers')->count();

            // Revenue statistics
            $revenueSubquery = Customer::selectRaw('customers.id, SUM(payments.amount) as total_revenue')
                ->leftJoin('payments', 'customers.id', '=', 'payments.customer_id')
                ->where('payments.status', 'completed')
                ->groupBy('customers.id');

            if ($this->currentTenant) {
                $revenueSubquery->where('customers.tenant_id', $this->currentTenant->id);
                $revenueSubquery->where('payments.tenant_id', $this->currentTenant->id);
            }

            // Apply period filter to revenue
            if ($period !== 'all') {
                $revenueSubquery->whereBetween('payments.created_at', [
                    $query->getQuery()->wheres[0]['value'] ?? now()->subYear(),
                    $query->getQuery()->wheres[1]['value'] ?? now()
                ]);
            }

            $revenueStats = $revenueSubquery->get();

            $totalRevenue = $revenueStats->sum('total_revenue') ?? 0;
            $avgRevenuePerCustomer = $customersWithPayments > 0 ? round($totalRevenue / $customersWithPayments, 2) : 0;

            // Get registration sources
            $registrationSources = Customer::selectRaw("metadata->>'registration_source' as source, COUNT(*) as count")
                ->when($this->currentTenant, function ($q) {
                    $q->where('tenant_id', $this->currentTenant->id);
                })
                ->when($period !== 'all', function ($q) use ($query) {
                    $q->whereBetween('created_at', [
                        $query->getQuery()->wheres[0]['value'] ?? now()->subYear(),
                        $query->getQuery()->wheres[1]['value'] ?? now()
                    ]);
                })
                ->whereNotNull('metadata->registration_source')
                ->groupByRaw("metadata->>'registration_source'")
                ->orderByDesc('count')
                ->limit(10)
                ->get()
                ->toArray();

            // Customer growth trend (last 7 days)
            $growthTrend = [];
            for ($i = 6; $i >= 0; $i--) {
                $date = now()->subDays($i)->format('Y-m-d');
                $dailyCustomers = Customer::whereDate('created_at', $date)
                    ->when($this->currentTenant, function ($q) {
                        $q->where('tenant_id', $this->currentTenant->id);
                    })
                    ->count();

                $dailyRevenue = Payment::where('status', 'completed')
                    ->whereDate('created_at', $date)
                    ->when($this->currentTenant, function ($q) {
                        $q->where('tenant_id', $this->currentTenant->id);
                    })
                    ->sum('amount') ?? 0;

                $growthTrend[] = [
                    'date' => $date,
                    'new_customers' => $dailyCustomers,
                    'revenue' => $dailyRevenue,
                ];
            }

            $responseData = [
                'success' => true,
                'data' => [
                    'period' => $period,
                    'total_customers' => $totalCustomers,
                    'active_customers' => $activeCustomers,
                    'new_customers' => $newCustomers,
                    'customers_with_payments' => $customersWithPayments,
                    'customers_with_vouchers' => $customersWithVouchers,
                    'conversion_rate' => $totalCustomers > 0 ? round(($customersWithPayments / $totalCustomers) * 100, 2) : 0,
                    'total_revenue' => $totalRevenue,
                    'avg_revenue_per_customer' => $avgRevenuePerCustomer,
                    'registration_sources' => $registrationSources,
                    'growth_trend' => $growthTrend,
                    'period_start' => $query->getQuery()->wheres[0]['value'] ?? null,
                    'period_end' => $query->getQuery()->wheres[1]['value'] ?? null,
                ]
            ];

            // Add tenant info to response if tenant exists
            if ($this->currentTenant) {
                $responseData['tenant'] = [
                    'id' => $this->currentTenant->id,
                    'code' => $this->currentTenant->code,
                    'name' => $this->currentTenant->name,
                ];
            }

            return response()->json($responseData);

        } catch (\Exception $e) {
            Log::channel('customer')->error('Failed to fetch customer statistics', [
                'tenant_id' => $this->currentTenant?->id,
                'error' => $e->getMessage(),
                'period' => $period
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch customer statistics',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Export customers (CSV)
     */
    public function export(Request $request): JsonResponse
    {
        try {
            $query = Customer::query()
                ->withCount(['payments', 'vouchers'])
                ->withSum('payments', 'amount')
                ->orderBy('created_at', 'desc');

            // Scope by tenant if current tenant exists
            if ($this->currentTenant) {
                $query->where('tenant_id', $this->currentTenant->id);
            }

            // Apply filters
            if ($request->has('is_active')) {
                $query->where('is_active', $request->get('is_active') === 'true');
            }

            if ($request->has('start_date')) {
                $query->whereDate('created_at', '>=', $request->get('start_date'));
            }

            if ($request->has('end_date')) {
                $query->whereDate('created_at', '<=', $request->get('end_date'));
            }

            $customers = $query->get();

            // Generate CSV content
            $csvData = "ID,Name,Phone,Email,Status,Total Payments,Total Vouchers,Total Spent,Avg Payment,First Payment,Last Payment,Created At,Tenant\n";

            foreach ($customers as $customer) {
                $firstPayment = $customer->payments()->orderBy('created_at')->first();
                $lastPayment = $customer->payments()->orderByDesc('created_at')->first();
                $avgPayment = $customer->payments_count > 0
                    ? round(($customer->payments_sum_amount ?? 0) / $customer->payments_count, 2)
                    : 0;

                $csvData .= sprintf(
                    "%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s\n",
                    $customer->id,
                    $customer->name,
                    $customer->phone,
                    $customer->email ?? 'N/A',
                    $customer->is_active ? 'Active' : 'Inactive',
                    $customer->payments_count,
                    $customer->vouchers_count,
                    $customer->payments_sum_amount ?? 0,
                    $avgPayment,
                    $firstPayment?->created_at?->format('Y-m-d H:i') ?? 'N/A',
                    $lastPayment?->created_at?->format('Y-m-d H:i') ?? 'N/A',
                    $customer->created_at->format('Y-m-d H:i'),
                    $customer->tenant_id ? 'Tenant-' . $customer->tenant_id : 'Global'
                );
            }

            // Generate filename with tenant prefix if applicable
            $filename = 'customers_' . ($this->currentTenant ? $this->currentTenant->code . '_' : '') . now()->format('Y-m-d') . '.csv';

            // Return CSV as downloadable response
            return response()->streamDownload(function () use ($csvData) {
                echo $csvData;
            }, $filename, [
                'Content-Type' => 'text/csv',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            ]);

        } catch (\Exception $e) {
            Log::channel('customer')->error('Failed to export customers', [
                'tenant_id' => $this->currentTenant?->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to export customers',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Format customer response
     */
    private function formatCustomerResponse(Customer $customer, bool $detailed = false): array
    {
        $response = [
            'id' => $customer->id,
            'uuid' => $customer->uuid,
            'name' => $customer->name,
            'phone' => $customer->phone,
            'email' => $customer->email,
            'is_active' => $customer->is_active,
            'created_at' => $customer->created_at->toISOString(),
            'updated_at' => $customer->updated_at->toISOString(),
            'payments_count' => $customer->payments_count ?? 0,
            'vouchers_count' => $customer->vouchers_count ?? 0,
            'total_spent' => $customer->payments_sum_amount ?? 0,
        ];

        if ($detailed) {
            $response['metadata'] = $customer->metadata ?? [];

            // Add calculated fields
            $firstPayment = $customer->payments()->orderBy('created_at')->first();
            $lastPayment = $customer->payments()->orderByDesc('created_at')->first();

            $response['customer_since'] = $firstPayment?->created_at?->toISOString() ?? $customer->created_at->toISOString();
            $response['last_activity'] = $lastPayment?->created_at?->toISOString() ?? $customer->updated_at->toISOString();
            $response['avg_payment_amount'] = $customer->payments_count > 0
                ? round(($customer->payments_sum_amount ?? 0) / $customer->payments_count, 2)
                : 0;
        }

        return $response;
    }

    /**
     * Calculate average time between payments for a customer
     */
    private function calculateAverageTimeBetweenPayments(Customer $customer): ?int
    {
        $payments = $customer->payments()
            ->where('status', 'completed')
            ->orderBy('created_at')
            ->get();

        if ($payments->count() < 2) {
            return null;
        }

        $totalDays = 0;
        $count = 0;

        for ($i = 1; $i < $payments->count(); $i++) {
            $daysBetween = $payments[$i]->created_at->diffInDays($payments[$i - 1]->created_at);
            $totalDays += $daysBetween;
            $count++;
        }

        return $count > 0 ? round($totalDays / $count) : null;
    }

    /**
     * Get customer's preferred package
     */
    private function getPreferredPackage(Customer $customer): ?string
    {
        $preferredPackage = Payment::where('customer_id', $customer->id)
            ->where('status', 'completed')
            ->selectRaw("metadata->>'package' as package, COUNT(*) as count")
            ->groupByRaw("metadata->>'package'")
            ->orderByDesc('count')
            ->first();

        return $preferredPackage?->package;
    }
}
