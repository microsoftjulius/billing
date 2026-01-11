<?php

namespace App\Http\Controllers\Api;

use App\Contracts\Payment\PaymentGatewayInterface;
use App\DTOs\Payment\PaymentRequestDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\PaymentRequest as ApiPaymentRequest;
use App\Jobs\ProcessSuccessfulPayment;
use App\Models\Payment;
use App\Models\Customer;
use App\Models\Tenant;
use App\Services\Payment\CollectUgService;
use App\Services\Payment\PaymentGatewayManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    private PaymentGatewayManager $gatewayManager;
    private ?Tenant $currentTenant;

    public function __construct(PaymentGatewayManager $gatewayManager)
    {
        $this->gatewayManager = $gatewayManager;
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
     * Initiate a new payment
     */
    public function initiate(ApiPaymentRequest $request): JsonResponse
    {
        DB::beginTransaction();

        try {
            // Get validated data with defaults
            $validated = $request->validated();

            // Add tenant context to metadata if tenant exists
            $customerMetadata = [
                'registration_source' => 'payment_api',
                'first_payment_at' => now()->toISOString(),
            ];

            $paymentMetadata = [
                'package' => $validated['package'],
                'validity_hours' => $validated['validity_hours'],
                'description' => $validated['description'],
                'request_ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'timestamp' => now()->toISOString(),
            ];

            // Add tenant context if available
            if ($this->currentTenant) {
                $customerMetadata['tenant_id'] = $this->currentTenant->id;
                $customerMetadata['tenant_code'] = $this->currentTenant->code;
                $paymentMetadata['tenant_id'] = $this->currentTenant->id;
                $paymentMetadata['tenant_code'] = $this->currentTenant->code;
                $paymentMetadata['tenant_name'] = $this->currentTenant->name;
            }

            // Find or create customer (scoped by tenant if applicable)
            $customerQuery = Customer::where('phone', $validated['phone']);

            if ($this->currentTenant) {
                // If tenant exists, create customer with tenant context
                $customer = Customer::firstOrCreate(
                    [
                        'phone' => $validated['phone'],
                        'tenant_id' => $this->currentTenant->id,
                    ],
                    [
                        'uuid' => Str::orderedUuid(),
                        'name' => $validated['name'],
                        'email' => $validated['email'] ?? null,
                        'is_active' => true,
                        'metadata' => $customerMetadata,
                        'tenant_id' => $this->currentTenant->id,
                    ]
                );
            } else {
                // Global customer (no tenant)
                $customer = Customer::firstOrCreate(
                    ['phone' => $validated['phone']],
                    [
                        'uuid' => Str::orderedUuid(),
                        'name' => $validated['name'],
                        'email' => $validated['email'] ?? null,
                        'is_active' => true,
                        'metadata' => $customerMetadata,
                    ]
                );
            }

            // Generate unique transaction ID with tenant prefix if applicable
            $tenantPrefix = $this->currentTenant ? 'TENANT-' . $this->currentTenant->code . '-' : 'PAY-';
            $transactionId = $tenantPrefix . now()->format('Ymd') . '-' . strtoupper(Str::random(8));

            // Create payment record
            $paymentData = [
                'uuid' => Str::orderedUuid(),
                'customer_id' => $customer->id,
                'transaction_id' => $transactionId,
                'amount' => $validated['amount'],
                'currency' => $validated['currency'],
                'status' => 'pending',
                'payment_method' => 'mobile_money',
                'provider' => 'collectug',
                'metadata' => $paymentMetadata,
            ];

            // Add tenant_id to payment if tenant exists
            if ($this->currentTenant) {
                $paymentData['tenant_id'] = $this->currentTenant->id;
            }

            $payment = Payment::create($paymentData);

            // Prepare payment request DTO with tenant context
            $metadata = [
                'package' => $validated['package'],
                'validity_hours' => $validated['validity_hours'],
                'customer_id' => $customer->id,
                'payment_id' => $payment->id,
                'transaction_id' => $transactionId,
                'customer_name' => $validated['name'],
            ];

            // Add tenant to metadata for gateway
            if ($this->currentTenant) {
                $metadata['tenant_id'] = $this->currentTenant->id;
                $metadata['tenant_code'] = $this->currentTenant->code;
            }

            $paymentRequest = new PaymentRequestDTO(
                amount: $validated['amount'],
                currency: $validated['currency'],
                customerPhone: $validated['phone'],
                customerEmail: $validated['email'] ?? null,
                description: $validated['description'],
                metadata: $metadata
            );

            $logContext = [
                'payment_id' => $payment->id,
                'transaction_id' => $transactionId,
                'amount' => $validated['amount'],
                'customer_phone' => $validated['phone']
            ];

            // Add tenant context to logs
            if ($this->currentTenant) {
                $logContext['tenant_id'] = $this->currentTenant->id;
                $logContext['tenant_code'] = $this->currentTenant->code;
            }

            Log::channel('payment')->info('Initiating payment with gateway manager', $logContext);

            // Initialize payment with gateway manager
            $paymentResponse = $this->gatewayManager->processPayment($paymentRequest);

            // Check if payment initiation was successful
            if (!$paymentResponse->success) {
                throw new \Exception('Payment gateway returned error: ' . $paymentResponse->message);
            }

            // Update payment with gateway reference and response
            $payment->update([
                'reference' => $paymentResponse->reference,
                'gateway_response' => $paymentResponse->providerResponse,
            ]);

            DB::commit();

            $successLogContext = [
                'payment_id' => $payment->id,
                'transaction_id' => $transactionId,
                'gateway_reference' => $paymentResponse->reference,
                'customer_id' => $customer->id,
                'amount' => $validated['amount']
            ];

            // Add tenant context to success logs
            if ($this->currentTenant) {
                $successLogContext['tenant_id'] = $this->currentTenant->id;
            }

            Log::channel('payment')->info('Payment initiated successfully', $successLogContext);

            // Prepare response data
            $responseData = [
                'payment_id' => $payment->id,
                'transaction_id' => $transactionId,
                'reference' => $paymentResponse->reference,
                'amount' => $payment->amount,
                'currency' => $payment->currency,
                'customer' => [
                    'id' => $customer->id,
                    'name' => $customer->name,
                    'phone' => $customer->phone,
                    'email' => $customer->email,
                ],
                'requires_mobile_confirmation' => $paymentResponse->requiresMobileConfirmation,
                'instructions' => 'Please check your phone to confirm the payment',
                'created_at' => $payment->created_at->toISOString(),
            ];

            // Add tenant to response if exists
            if ($this->currentTenant) {
                $responseData['tenant'] = [
                    'id' => $this->currentTenant->id,
                    'code' => $this->currentTenant->code,
                    'name' => $this->currentTenant->name,
                ];
                $responseData['check_status_url'] = route('api.payments.verify', [
                    'tenant' => $this->currentTenant->code,
                    'transactionId' => $transactionId
                ]);
            } else {
                $responseData['check_status_url'] = route('api.payments.verify', [
                    'transactionId' => $transactionId
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => $paymentResponse->message,
                'data' => $responseData
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();

            $errorLogContext = [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->validated(),
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent()
            ];

            // Add tenant context to error logs
            if ($this->currentTenant) {
                $errorLogContext['tenant_id'] = $this->currentTenant->id;
            }

            Log::channel('payment')->error('Payment initiation failed', $errorLogContext);

            return response()->json([
                'success' => false,
                'message' => 'Payment initiation failed. Please try again.',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Verify payment status (with optional tenant parameter)
     */
    public function verify(Request $request, ?string $tenantCode = null, ?string $transactionId = null): JsonResponse
    {
        // Handle different route patterns
        if (!$transactionId && $request->route('transactionId')) {
            $transactionId = $request->route('transactionId');
        }

        if (!$tenantCode && $request->route('tenant')) {
            $tenantCode = $request->route('tenant');
        }

        try {
            $query = Payment::with(['customer', 'voucher']);

            // If tenant code is provided, scope by tenant
            if ($tenantCode) {
                $tenant = Tenant::where('code', $tenantCode)->firstOrFail();
                $query->where('tenant_id', $tenant->id);
                $logTenantContext = ['tenant_id' => $tenant->id, 'tenant_code' => $tenantCode];
            } else {
                $tenant = null;
                $logTenantContext = [];
            }

            $payment = $query->where('transaction_id', $transactionId)->firstOrFail();

            $logContext = array_merge([
                'transaction_id' => $transactionId,
                'payment_id' => $payment->id,
                'current_status' => $payment->status
            ], $logTenantContext);

            Log::channel('payment')->info('Verifying payment', $logContext);

            // If already completed, return current status
            if ($payment->status === 'completed') {
                return response()->json([
                    'success' => true,
                    'status' => 'completed',
                    'message' => 'Payment already completed',
                    'data' => $this->formatPaymentResponse($payment, $tenant)
                ]);
            }

            // Verify payment with gateway
            $verificationResponse = $this->paymentGateway->verifyPayment($payment->reference ?? $transactionId);

            if ($verificationResponse->success && $payment->status !== 'completed') {
                // Mark payment as completed
                $payment->markAsCompleted();

                // Update gateway response
                $payment->updateGatewayResponse([
                    'verification_response' => $verificationResponse->providerResponse,
                    'verified_at' => now()->toISOString(),
                ]);

                // Dispatch job to process voucher creation and SMS
                ProcessSuccessfulPayment::dispatch($payment, $verificationResponse);

                $verifiedLogContext = array_merge([
                    'payment_id' => $payment->id,
                    'transaction_id' => $transactionId,
                    'amount' => $payment->amount,
                    'verified_at' => now()->toISOString()
                ], $logTenantContext);

                Log::channel('payment')->info('Payment verified successfully', $verifiedLogContext);
            } elseif (!$verificationResponse->success && $payment->status === 'pending') {
                // Check if payment has been pending for too long (30 minutes)
                if ($payment->created_at->diffInMinutes(now()) > 30) {
                    $payment->markAsFailed('Payment verification timeout');

                    $timeoutLogContext = array_merge([
                        'payment_id' => $payment->id,
                        'transaction_id' => $transactionId,
                        'pending_duration' => $payment->created_at->diffInMinutes(now()) . ' minutes'
                    ], $logTenantContext);

                    Log::channel('payment')->warning('Payment marked as failed due to timeout', $timeoutLogContext);
                }
            }

            return response()->json([
                'success' => $verificationResponse->success,
                'status' => $payment->status,
                'message' => $verificationResponse->message,
                'data' => $this->formatPaymentResponse($payment, $tenant, $verificationResponse)
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            $notFoundContext = ['transaction_id' => $transactionId, 'error' => $e->getMessage()];
            if ($tenantCode) {
                $notFoundContext['tenant_code'] = $tenantCode;
            }

            Log::channel('payment')->warning('Payment not found for verification', $notFoundContext);

            return response()->json([
                'success' => false,
                'message' => 'Payment not found',
                'error' => 'Invalid transaction ID' . ($tenantCode ? ' or tenant code' : '')
            ], 404);

        } catch (\Exception $e) {
            $errorContext = [
                'transaction_id' => $transactionId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ];
            if ($tenantCode) {
                $errorContext['tenant_code'] = $tenantCode;
            }

            Log::channel('payment')->error('Payment verification failed', $errorContext);

            return response()->json([
                'success' => false,
                'message' => 'Payment verification failed',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Handle payment callback from gateway
     */
    public function callback(Request $request): JsonResponse
    {
        $payload = $request->all();

        Log::channel('payment')->info('Payment gateway callback received', [
            'payload' => $payload,
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent()
        ]);

        // Verify webhook signature if provided
        if (isset($payload['signature'])) {
            $isValid = $this->verifyWebhookSignature($payload);

            if (!$isValid) {
                Log::channel('payment')->warning('Invalid webhook signature', [
                    'payload' => $payload,
                    'received_signature' => $payload['signature']
                ]);

                return response()->json(['error' => 'Invalid signature'], 403);
            }
        }

        // Extract transaction reference from payload
        $reference = $this->extractReferenceFromPayload($payload);

        if (!$reference) {
            Log::channel('payment')->error('Reference not found in callback payload', [
                'payload' => $payload
            ]);

            return response()->json(['error' => 'Transaction reference missing'], 400);
        }

        DB::beginTransaction();

        try {
            // Find payment by reference
            $payment = Payment::where('reference', $reference)->first();

            if (!$payment) {
                // Try to find by transaction ID as fallback
                $payment = Payment::where('transaction_id', $reference)->first();

                if (!$payment) {
                    Log::channel('payment')->warning('Payment not found for callback', [
                        'reference' => $reference,
                        'payload' => $payload
                    ]);

                    DB::rollBack();
                    return response()->json(['error' => 'Payment not found'], 404);
                }
            }

            // Determine status from payload
            $status = $this->determineStatusFromPayload($payload);

            // Add tenant context to logs
            $logContext = [
                'payment_id' => $payment->id,
                'reference' => $reference,
            ];

            if ($payment->tenant_id) {
                $logContext['tenant_id'] = $payment->tenant_id;
            }

            // Update payment based on status
            switch ($status) {
                case 'completed':
                    if ($payment->status !== 'completed') {
                        $payment->update([
                            'status' => 'completed',
                            'paid_at' => now(),
                            'gateway_response' => array_merge(
                                $payment->gateway_response ?? [],
                                ['callback_data' => $payload, 'callback_received_at' => now()->toISOString()]
                            )
                        ]);

                        // Dispatch job to process voucher creation
                        ProcessSuccessfulPayment::dispatch($payment);

                        $logContext['amount'] = $payment->amount;
                        Log::channel('payment')->info('Payment completed via callback', $logContext);
                    }
                    break;

                case 'failed':
                    if ($payment->status === 'pending') {
                        $payment->update([
                            'status' => 'failed',
                            'failed_at' => now(),
                            'gateway_response' => array_merge(
                                $payment->gateway_response ?? [],
                                ['callback_data' => $payload, 'callback_received_at' => now()->toISOString()]
                            )
                        ]);

                        $logContext['failure_data'] = $payload;
                        Log::channel('payment')->info('Payment failed via callback', $logContext);
                    }
                    break;

                default:
                    // Update gateway response only for other statuses
                    $payment->update([
                        'gateway_response' => array_merge(
                            $payment->gateway_response ?? [],
                            ['callback_data' => $payload, 'callback_received_at' => now()->toISOString()]
                        )
                    ]);

                    $logContext['status'] = $status;
                    Log::channel('payment')->debug('Payment callback received with status: ' . $status, $logContext);
            }

            DB::commit();

            return response()->json(['success' => true, 'message' => 'Callback processed successfully']);

        } catch (\Exception $e) {
            DB::rollBack();

            Log::channel('payment')->error('Callback processing failed', [
                'reference' => $reference,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'payload' => $payload
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Callback processing failed',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * List payments with pagination (with tenant filtering)
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $perPage = min($request->get('per_page', 15), 100); // Max 100 per page
            $page = $request->get('page', 1);

            $query = Payment::with(['customer', 'voucher'])
                ->orderBy('created_at', 'desc');

            // Scope by tenant if current tenant exists
            if ($this->currentTenant) {
                $query->where('tenant_id', $this->currentTenant->id);
            }

            // Apply filters
            if ($request->has('status')) {
                $query->where('status', $request->get('status'));
            }

            if ($request->has('customer_id')) {
                $query->where('customer_id', $request->get('customer_id'));
            }

            if ($request->has('customer_phone')) {
                $query->whereHas('customer', function ($q) use ($request) {
                    $q->where('phone', $request->get('customer_phone'));
                });
            }

            if ($request->has('start_date')) {
                $query->whereDate('created_at', '>=', $request->get('start_date'));
            }

            if ($request->has('end_date')) {
                $query->whereDate('created_at', '<=', $request->get('end_date'));
            }

            if ($request->has('min_amount')) {
                $query->where('amount', '>=', $request->get('min_amount'));
            }

            if ($request->has('max_amount')) {
                $query->where('amount', '<=', $request->get('max_amount'));
            }

            if ($request->has('package')) {
                $query->whereJsonContains('metadata->package', $request->get('package'));
            }

            // Filter by tenant_id if explicitly requested
            if ($request->has('tenant_id') && !$this->currentTenant) {
                $query->where('tenant_id', $request->get('tenant_id'));
            }

            $payments = $query->paginate($perPage, ['*'], 'page', $page);

            $responseData = [
                'success' => true,
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
                        'total_amount' => $payments->sum('amount'),
                        'completed_count' => $payments->where('status', 'completed')->count(),
                        'pending_count' => $payments->where('status', 'pending')->count(),
                        'failed_count' => $payments->where('status', 'failed')->count(),
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
                // Add tenant-specific summary
                $responseData['data']['summary']['unique_customers'] = $payments->unique('customer_id')->count();
            }

            return response()->json($responseData);

        } catch (\Exception $e) {
            $errorContext = [
                'error' => $e->getMessage(),
                'filters' => $request->all()
            ];

            if ($this->currentTenant) {
                $errorContext['tenant_id'] = $this->currentTenant->id;
            }

            Log::channel('payment')->error('Failed to fetch payments', $errorContext);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch payments',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Get payment details (with optional tenant context)
     */
    public function show(Request $request, ?string $tenantCode = null, ?string $transactionId = null): JsonResponse
    {
        // Handle different route patterns
        if (!$transactionId && $request->route('transactionId')) {
            $transactionId = $request->route('transactionId');
        }

        if (!$tenantCode && $request->route('tenant')) {
            $tenantCode = $request->route('tenant');
        }

        try {
            $query = Payment::with(['customer', 'voucher']);

            // If tenant code is provided, scope by tenant
            if ($tenantCode) {
                $tenant = Tenant::where('code', $tenantCode)->firstOrFail();
                $query->where('tenant_id', $tenant->id);
            } else {
                $tenant = null;
            }

            $payment = $query->where('transaction_id', $transactionId)->firstOrFail();

            $response = [
                'success' => true,
                'data' => $this->formatPaymentResponse($payment, $tenant)
            ];

            // Add tenant info to response if tenant exists
            if ($tenant) {
                $response['tenant'] = [
                    'id' => $tenant->id,
                    'code' => $tenant->code,
                    'name' => $tenant->name,
                ];
            }

            return response()->json($response);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Payment not found'
            ], 404);

        } catch (\Exception $e) {
            $errorContext = ['transaction_id' => $transactionId, 'error' => $e->getMessage()];
            if ($tenantCode) {
                $errorContext['tenant_code'] = $tenantCode;
            }

            Log::channel('payment')->error('Failed to fetch payment details', $errorContext);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch payment details',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Get payment statistics (with tenant filtering)
     */
    public function statistics(Request $request): JsonResponse
    {
        try {
            $period = $request->get('period', 'today'); // today, week, month, year, custom

            $query = Payment::query();

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

            $totalPayments = $query->count();
            $completedPayments = (clone $query)->where('status', 'completed')->count();
            $pendingPayments = (clone $query)->where('status', 'pending')->count();
            $failedPayments = (clone $query)->where('status', 'failed')->count();

            $totalRevenue = (clone $query)->where('status', 'completed')->sum('amount') ?? 0;
            $averageAmount = $completedPayments > 0 ? round($totalRevenue / $completedPayments, 2) : 0;

            // Popular packages
            $popularQuery = Payment::where('status', 'completed');

            if ($this->currentTenant) {
                $popularQuery->where('tenant_id', $this->currentTenant->id);
            }

            $popularPackages = $popularQuery
                ->selectRaw("metadata->>'package' as package, COUNT(*) as count, SUM(amount) as revenue")
                ->groupByRaw("metadata->>'package'")
                ->orderByDesc('count')
                ->limit(5)
                ->get()
                ->toArray();

            $responseData = [
                'success' => true,
                'data' => [
                    'period' => $period,
                    'total_payments' => $totalPayments,
                    'completed_payments' => $completedPayments,
                    'pending_payments' => $pendingPayments,
                    'failed_payments' => $failedPayments,
                    'success_rate' => $totalPayments > 0 ? round(($completedPayments / $totalPayments) * 100, 2) : 0,
                    'total_revenue' => $totalRevenue,
                    'average_amount' => $averageAmount,
                    'popular_packages' => $popularPackages,
                    'period_start' => $query->getQuery()->wheres[0]['value'] ?? null,
                    'period_end' => $query->getQuery()->wheres[1]['value'] ?? null,
                ]
            ];

            // Add tenant-specific statistics
            if ($this->currentTenant) {
                $uniqueCustomers = (clone $query)->distinct('customer_id')->count('customer_id');
                $responseData['data']['unique_customers'] = $uniqueCustomers;
                $responseData['tenant'] = [
                    'id' => $this->currentTenant->id,
                    'code' => $this->currentTenant->code,
                    'name' => $this->currentTenant->name,
                ];
            }

            return response()->json($responseData);

        } catch (\Exception $e) {
            $errorContext = [
                'error' => $e->getMessage(),
                'period' => $period
            ];

            if ($this->currentTenant) {
                $errorContext['tenant_id'] = $this->currentTenant->id;
            }

            Log::channel('payment')->error('Failed to fetch payment statistics', $errorContext);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch payment statistics',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Export payments (with tenant filtering)
     */
    public function export(Request $request): JsonResponse
    {
        try {
            $query = Payment::with(['customer'])
                ->orderBy('created_at', 'desc');

            // Scope by tenant if current tenant exists
            if ($this->currentTenant) {
                $query->where('tenant_id', $this->currentTenant->id);
            }

            // Apply filters
            if ($request->has('status')) {
                $query->where('status', $request->get('status'));
            }

            if ($request->has('start_date')) {
                $query->whereDate('created_at', '>=', $request->get('start_date'));
            }

            if ($request->has('end_date')) {
                $query->whereDate('created_at', '<=', $request->get('end_date'));
            }

            $payments = $query->get();

            // Generate CSV content
            $csvData = "Transaction ID,Amount,Currency,Status,Customer Name,Customer Phone,Customer Email,Package,Payment Method,Created At,Paid At,Tenant\n";

            foreach ($payments as $payment) {
                $csvData .= sprintf(
                    "%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s\n",
                    $payment->transaction_id,
                    $payment->amount,
                    $payment->currency,
                    $payment->status,
                    $payment->customer?->name ?? 'N/A',
                    $payment->customer?->phone ?? 'N/A',
                    $payment->customer?->email ?? 'N/A',
                    $payment->metadata['package'] ?? 'N/A',
                    $payment->payment_method,
                    $payment->created_at->toDateTimeString(),
                    $payment->paid_at?->toDateTimeString() ?? 'N/A',
                    $payment->tenant_id ? 'Tenant-' . $payment->tenant_id : 'Global'
                );
            }

            // Generate filename with tenant prefix if applicable
            $filename = 'payments_' . ($this->currentTenant ? $this->currentTenant->code . '_' : '') . now()->format('Y-m-d') . '.csv';

            // Return CSV as downloadable response
            return response()->streamDownload(function () use ($csvData) {
                echo $csvData;
            }, $filename, [
                'Content-Type' => 'text/csv',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            ]);

        } catch (\Exception $e) {
            $errorContext = ['error' => $e->getMessage()];

            if ($this->currentTenant) {
                $errorContext['tenant_id'] = $this->currentTenant->id;
            }

            Log::channel('payment')->error('Failed to export payments', $errorContext);

            return response()->json([
                'success' => false,
                'message' => 'Failed to export payments',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Verify webhook signature
     */
    private function verifyWebhookSignature(array $payload): bool
    {
        $secret = config('services.collectug.webhook_secret');

        if (!$secret) {
            Log::channel('payment')->warning('Webhook secret not configured');
            return false;
        }

        $receivedSignature = $payload['signature'];
        unset($payload['signature']);

        $calculatedSignature = hash_hmac('sha256', json_encode($payload), $secret);

        return hash_equals($calculatedSignature, $receivedSignature);
    }

    /**
     * Extract reference from callback payload
     */
    private function extractReferenceFromPayload(array $payload): ?string
    {
        // Try different possible reference fields
        $possibleFields = [
            'transaction_id',
            'reference',
            'transaction_reference',
            'id',
            'payment_reference',
            'checkout_request_id',
            'merchant_reference'
        ];

        foreach ($possibleFields as $field) {
            if (isset($payload[$field])) {
                return (string) $payload[$field];
            }

            // Check nested in transaction object
            if (isset($payload['transaction'][$field])) {
                return (string) $payload['transaction'][$field];
            }
        }

        return null;
    }

    /**
     * Determine status from callback payload
     */
    private function determineStatusFromPayload(array $payload): string
    {
        $status = $payload['status'] ?? $payload['transaction']['status'] ?? 'unknown';

        $statusMap = [
            'completed' => 'completed',
            'success' => 'completed',
            'successful' => 'completed',
            'paid' => 'completed',
            'confirmed' => 'completed',
            'failed' => 'failed',
            'error' => 'failed',
            'rejected' => 'failed',
            'cancelled' => 'failed',
            'pending' => 'pending',
            'processing' => 'pending',
            'initiated' => 'pending',
        ];

        return $statusMap[strtolower($status)] ?? 'unknown';
    }

    /**
     * Format payment response
     */
    private function formatPaymentResponse(Payment $payment, ?Tenant $tenant = null, $verificationResponse = null): array
    {
        $response = [
            'payment_id' => $payment->id,
            'transaction_id' => $payment->transaction_id,
            'reference' => $payment->reference,
            'amount' => $payment->amount,
            'currency' => $payment->currency,
            'status' => $payment->status,
            'payment_method' => $payment->payment_method,
            'provider' => $payment->provider,
            'created_at' => $payment->created_at->toISOString(),
            'paid_at' => $payment->paid_at?->toISOString(),
            'failed_at' => $payment->failed_at?->toISOString(),
            'customer' => $payment->customer ? [
                'id' => $payment->customer->id,
                'name' => $payment->customer->name,
                'phone' => $payment->customer->phone,
                'email' => $payment->customer->email,
            ] : null,
            'package' => $payment->metadata['package'] ?? null,
            'validity_hours' => $payment->metadata['validity_hours'] ?? null,
            'description' => $payment->metadata['description'] ?? null,
        ];

        // Add tenant info if available
        if ($tenant || $payment->tenant_id) {
            $tenantInfo = $tenant ?: Tenant::find($payment->tenant_id);
            if ($tenantInfo) {
                $response['tenant'] = [
                    'id' => $tenantInfo->id,
                    'code' => $tenantInfo->code,
                    'name' => $tenantInfo->name,
                ];
            }
        }

        // Add voucher info if exists
        if ($payment->voucher) {
            $response['voucher'] = [
                'id' => $payment->voucher->id,
                'code' => $payment->voucher->code,
                'profile' => $payment->voucher->profile,
                'validity_hours' => $payment->voucher->validity_hours,
                'expires_at' => $payment->voucher->expires_at?->toISOString(),
                'status' => $payment->voucher->status,
                'sms_sent_at' => $payment->voucher->sms_sent_at?->toISOString(),
            ];
        }

        // Add verification response if provided
        if ($verificationResponse) {
            $response['verification'] = [
                'success' => $verificationResponse->success,
                'message' => $verificationResponse->message,
                'requires_action' => $verificationResponse->requiresMobileConfirmation ?? false,
            ];
        }

        return $response;
    }
}
