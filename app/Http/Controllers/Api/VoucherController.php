<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\Voucher;
use App\Services\VoucherService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class VoucherController extends Controller
{
    private VoucherService $voucherService;
    private ?Tenant $currentTenant;

    public function __construct(VoucherService $voucherService)
    {
        $this->voucherService = $voucherService;
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
     * Get voucher by code
     */
    public function show(string $code): JsonResponse
    {
        try {
            $query = Voucher::with(['customer', 'payment'])
                ->where('code', $code);

            // Scope by tenant if current tenant exists
            if ($this->currentTenant) {
                $query->where('tenant_id', $this->currentTenant->id);
            }

            $voucher = $query->firstOrFail();

            // Get usage information from router
            $usage = $this->voucherService->getVoucherUsage($code);

            $response = [
                'success' => true,
                'data' => [
                    'voucher' => [
                        'id' => $voucher->id,
                        'code' => $voucher->code,
                        'profile' => $voucher->profile,
                        'validity_hours' => $voucher->validity_hours,
                        'data_limit_mb' => $voucher->data_limit_mb,
                        'data_limit_formatted' => $voucher->getDataLimitFormattedAttribute(),
                        'price' => $voucher->price,
                        'currency' => $voucher->currency,
                        'status' => $voucher->status,
                        'activated_at' => $voucher->activated_at?->toISOString(),
                        'expires_at' => $voucher->expires_at?->toISOString(),
                        'sms_sent_at' => $voucher->sms_sent_at?->toISOString(),
                        'created_at' => $voucher->created_at->toISOString(),
                        'remaining_hours' => $voucher->getRemainingHoursAttribute(),
                        'remaining_time_formatted' => $voucher->getRemainingTimeAttribute(),
                        'is_active' => $voucher->getIsActiveAttribute(),
                        'metadata' => $voucher->metadata,
                    ],
                    'customer' => $voucher->customer ? [
                        'id' => $voucher->customer->id,
                        'name' => $voucher->customer->name,
                        'phone' => $voucher->customer->phone,
                        'email' => $voucher->customer->email,
                    ] : null,
                    'payment' => $voucher->payment ? [
                        'id' => $voucher->payment->id,
                        'transaction_id' => $voucher->payment->transaction_id,
                        'amount' => $voucher->payment->amount,
                        'currency' => $voucher->payment->currency,
                        'paid_at' => $voucher->payment->paid_at?->toISOString(),
                    ] : null,
                    'usage' => $usage['usage'] ?? [],
                    'connections' => $usage['connections'] ?? [],
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
                'message' => 'Voucher not found'
            ], 404);

        } catch (\Exception $e) {
            Log::channel('voucher')->error('Failed to fetch voucher', [
                'code' => $code,
                'tenant_id' => $this->currentTenant?->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch voucher details',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Get voucher usage statistics
     */
    public function usage(string $code): JsonResponse
    {
        try {
            // First verify voucher exists and belongs to tenant if applicable
            $query = Voucher::where('code', $code);

            if ($this->currentTenant) {
                $query->where('tenant_id', $this->currentTenant->id);
            }

            $voucher = $query->firstOrFail();

            // Get detailed usage from service
            $usageData = $this->voucherService->getVoucherUsage($code);

            if (isset($usageData['error'])) {
                throw new \Exception($usageData['error']);
            }

            return response()->json([
                'success' => true,
                'data' => $usageData
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Voucher not found'
            ], 404);

        } catch (\Exception $e) {
            Log::channel('voucher')->error('Failed to get voucher usage', [
                'code' => $code,
                'tenant_id' => $this->currentTenant?->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to get voucher usage',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Disable a voucher
     */
    public function disable(string $code): JsonResponse
    {
        try {
            // Verify voucher exists and belongs to tenant if applicable
            $query = Voucher::where('code', $code);

            if ($this->currentTenant) {
                $query->where('tenant_id', $this->currentTenant->id);
            }

            $voucher = $query->firstOrFail();

            // Disable voucher using service
            $disabled = $this->voucherService->disableVoucher($code);

            if (!$disabled) {
                throw new \Exception('Failed to disable voucher on router');
            }

            Log::channel('voucher')->info('Voucher disabled via API', [
                'voucher_id' => $voucher->id,
                'code' => $code,
                'tenant_id' => $this->currentTenant?->id,
                'disabled_by' => auth()->id() ?? 'system',
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Voucher disabled successfully',
                'data' => [
                    'code' => $code,
                    'status' => 'disabled',
                    'disabled_at' => now()->toISOString(),
                ]
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Voucher not found'
            ], 404);

        } catch (\Exception $e) {
            Log::channel('voucher')->error('Failed to disable voucher', [
                'code' => $code,
                'tenant_id' => $this->currentTenant?->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to disable voucher',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Renew a voucher (extend validity)
     */
    public function renew(string $code, Request $request): JsonResponse
    {
        try {
            // Validate request
            $validator = Validator::make($request->all(), [
                'additional_hours' => 'required|integer|min:1|max:720', // Max 30 days
            ]);

            if ($validator->fails()) {
                throw new ValidationException($validator);
            }

            $additionalHours = $request->input('additional_hours');

            // Verify voucher exists and belongs to tenant if applicable
            $query = Voucher::where('code', $code);

            if ($this->currentTenant) {
                $query->where('tenant_id', $this->currentTenant->id);
            }

            $voucher = $query->firstOrFail();

            // Renew voucher using service
            $result = $this->voucherService->renewVoucher($code, $additionalHours);

            if (!$result['success']) {
                throw new \Exception($result['message']);
            }

            Log::channel('voucher')->info('Voucher renewed via API', [
                'voucher_id' => $voucher->id,
                'code' => $code,
                'additional_hours' => $additionalHours,
                'tenant_id' => $this->currentTenant?->id,
                'renewed_by' => auth()->id() ?? 'system',
            ]);

            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'data' => $result['voucher']
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
                'message' => 'Voucher not found'
            ], 404);

        } catch (\Exception $e) {
            Log::channel('voucher')->error('Failed to renew voucher', [
                'code' => $code,
                'additional_hours' => $request->input('additional_hours'),
                'tenant_id' => $this->currentTenant?->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to renew voucher',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Sync voucher with router
     */
    public function sync(string $code): JsonResponse
    {
        try {
            // Verify voucher exists and belongs to tenant if applicable
            $query = Voucher::where('code', $code);

            if ($this->currentTenant) {
                $query->where('tenant_id', $this->currentTenant->id);
            }

            $voucher = $query->firstOrFail();

            // Sync voucher using service
            $result = $this->voucherService->syncWithRouter($code);

            $logData = [
                'voucher_id' => $voucher->id,
                'code' => $code,
                'tenant_id' => $this->currentTenant?->id,
                'action' => $result['action'] ?? 'unknown',
                'success' => $result['success'] ?? false,
                'synced_by' => auth()->id() ?? 'system',
            ];

            if ($result['success']) {
                Log::channel('voucher')->info('Voucher synced successfully', $logData);
            } else {
                Log::channel('voucher')->warning('Voucher sync failed', $logData);
            }

            return response()->json([
                'success' => $result['success'],
                'message' => $result['message'],
                'action' => $result['action'],
                'data' => [
                    'code' => $code,
                    'status' => $voucher->status,
                    'synced_at' => now()->toISOString(),
                ]
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Voucher not found'
            ], 404);

        } catch (\Exception $e) {
            Log::channel('voucher')->error('Failed to sync voucher', [
                'code' => $code,
                'tenant_id' => $this->currentTenant?->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to sync voucher with router',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * List vouchers with pagination and filtering
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $perPage = min($request->get('per_page', 15), 100);
            $page = $request->get('page', 1);

            $query = Voucher::with(['customer', 'payment'])
                ->orderBy('created_at', 'desc');

            // Scope by tenant if current tenant exists
            if ($this->currentTenant) {
                $query->where('tenant_id', $this->currentTenant->id);
            }

            // Apply filters
            if ($request->has('status')) {
                $query->where('status', $request->get('status'));
            }

            if ($request->has('profile')) {
                $query->where('profile', $request->get('profile'));
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

            if ($request->has('expired')) {
                if ($request->get('expired') === 'true') {
                    $query->expired();
                } elseif ($request->get('expired') === 'false') {
                    $query->where('expires_at', '>', now());
                }
            }

            if ($request->has('active')) {
                if ($request->get('active') === 'true') {
                    $query->active();
                } elseif ($request->get('active') === 'false') {
                    $query->where(function ($q) {
                        $q->where('expires_at', '<=', now())
                            ->orWhere('status', 'disabled');
                    });
                }
            }

            // Filter by tenant_id if explicitly requested (admin only)
            if ($request->has('tenant_id') && !$this->currentTenant && auth()->user()?->isAdmin()) {
                $query->where('tenant_id', $request->get('tenant_id'));
            }

            $vouchers = $query->paginate($perPage, ['*'], 'page', $page);

            $responseData = [
                'success' => true,
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
                        'total_revenue' => $vouchers->sum('price'),
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
            Log::channel('voucher')->error('Failed to fetch vouchers', [
                'tenant_id' => $this->currentTenant?->id,
                'error' => $e->getMessage(),
                'filters' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch vouchers',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Get voucher statistics
     */
    public function statistics(Request $request): JsonResponse
    {
        try {
            $period = $request->get('period', 'today'); // today, week, month, year, custom

            // Get statistics from service
            $serviceStats = $this->voucherService->getVoucherStatistics();

            // Get period-specific statistics
            $query = Voucher::query();

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

            $periodStats = [
                'total_vouchers' => $query->count(),
                'active_vouchers' => (clone $query)->where('status', 'active')->count(),
                'total_revenue' => (clone $query)->where('status', 'active')->sum('price') ?? 0,
                'average_price' => function($query) {
                    $count = $query->count();
                    $sum = $query->sum('price') ?? 0;
                    return $count > 0 ? round($sum / $count, 2) : 0;
                },
                'top_profiles' => (clone $query)
                    ->select('profile', \DB::raw('count(*) as count'), \DB::raw('sum(price) as revenue'))
                    ->groupBy('profile')
                    ->orderByDesc('count')
                    ->limit(5)
                    ->get()
                    ->toArray(),
            ];

            // Calculate average price for period
            $periodStats['average_price'] = $periodStats['total_vouchers'] > 0
                ? round($periodStats['total_revenue'] / $periodStats['total_vouchers'], 2)
                : 0;

            $responseData = [
                'success' => true,
                'data' => [
                    'overall_statistics' => $serviceStats,
                    'period_statistics' => [
                        'period' => $period,
                        ...$periodStats,
                        'period_start' => $query->getQuery()->wheres[0]['value'] ?? null,
                        'period_end' => $query->getQuery()->wheres[1]['value'] ?? null,
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
            Log::channel('voucher')->error('Failed to fetch voucher statistics', [
                'tenant_id' => $this->currentTenant?->id,
                'error' => $e->getMessage(),
                'period' => $request->get('period')
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch voucher statistics',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Batch generate vouchers (admin only)
     */
    public function batchGenerate(Request $request): JsonResponse
    {
        // Check if user is admin or has permission
        if (!auth()->check() || !auth()->user()->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 403);
        }

        try {
            // Validate request
            $validator = Validator::make($request->all(), [
                'vouchers' => 'required|array|min:1',
                'vouchers.*.quantity' => 'required|integer|min:1|max:100',
                'vouchers.*.profile' => 'required|string|max:50',
                'vouchers.*.validity_hours' => 'required|integer|min:1|max:720',
                'vouchers.*.price' => 'nullable|numeric|min:0',
                'vouchers.*.data_limit_mb' => 'nullable|integer|min:1',
            ]);

            if ($validator->fails()) {
                throw new ValidationException($validator);
            }

            $batchData = $request->input('vouchers');

            // Add tenant context if current tenant exists
            if ($this->currentTenant) {
                foreach ($batchData as &$item) {
                    $item['metadata'] = array_merge($item['metadata'] ?? [], [
                        'tenant_id' => $this->currentTenant->id,
                        'tenant_code' => $this->currentTenant->code,
                    ]);
                }
            }

            // Generate vouchers
            $result = $this->voucherService->batchGenerateVouchers($batchData);

            $logData = [
                'total' => $result['total'],
                'successful' => $result['successful'],
                'failed' => $result['failed'],
                'tenant_id' => $this->currentTenant?->id,
                'generated_by' => auth()->id(),
            ];

            if ($result['failed'] > 0) {
                Log::channel('voucher')->warning('Batch voucher generation completed with errors', $logData);
            } else {
                Log::channel('voucher')->info('Batch voucher generation completed successfully', $logData);
            }

            return response()->json([
                'success' => $result['failed'] === 0,
                'message' => $result['failed'] === 0
                    ? 'All vouchers generated successfully'
                    : 'Some vouchers failed to generate',
                'data' => $result
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);

        } catch (\Exception $e) {
            Log::channel('voucher')->error('Batch voucher generation failed', [
                'tenant_id' => $this->currentTenant?->id,
                'error' => $e->getMessage(),
                'data' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to generate vouchers',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Export vouchers (CSV)
     */
    public function export(Request $request): JsonResponse
    {
        try {
            $query = Voucher::with(['customer', 'payment'])
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

            $vouchers = $query->get();

            // Generate CSV content
            $csvData = "Code,Password,Profile,Validity Hours,Data Limit (MB),Price,Currency,Status,Customer Name,Customer Phone,Customer Email,Activated At,Expires At,SMS Sent At,Created At,Tenant\n";

            foreach ($vouchers as $voucher) {
                $csvData .= sprintf(
                    "%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s\n",
                    $voucher->code,
                    $voucher->password,
                    $voucher->profile,
                    $voucher->validity_hours,
                    $voucher->data_limit_mb ?? 'Unlimited',
                    $voucher->price ?? '0.00',
                    $voucher->currency,
                    $voucher->status,
                    $voucher->customer?->name ?? 'N/A',
                    $voucher->customer?->phone ?? 'N/A',
                    $voucher->customer?->email ?? 'N/A',
                    $voucher->activated_at?->toDateTimeString() ?? 'N/A',
                    $voucher->expires_at?->toDateTimeString() ?? 'N/A',
                    $voucher->sms_sent_at?->toDateTimeString() ?? 'N/A',
                    $voucher->created_at->toDateTimeString(),
                    $voucher->tenant_id ? 'Tenant-' . $voucher->tenant_id : 'Global'
                );
            }

            // Generate filename with tenant prefix if applicable
            $filename = 'vouchers_' . ($this->currentTenant ? $this->currentTenant->code . '_' : '') . now()->format('Y-m-d') . '.csv';

            // Return CSV as downloadable response
            return response()->streamDownload(function () use ($csvData) {
                echo $csvData;
            }, $filename, [
                'Content-Type' => 'text/csv',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            ]);

        } catch (\Exception $e) {
            Log::channel('voucher')->error('Failed to export vouchers', [
                'tenant_id' => $this->currentTenant?->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to export vouchers',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Search vouchers by code, customer phone, or customer name
     */
    public function search(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'query' => 'required|string|min:2|max:100',
                'limit' => 'nullable|integer|min:1|max:50',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $query = $request->get('query');
            $limit = $request->get('limit', 10);

            $vouchers = Voucher::with(['customer'])
                ->where(function ($q) use ($query) {
                    $q->where('code', 'LIKE', "%{$query}%")
                        ->orWhereHas('customer', function ($q) use ($query) {
                            $q->where('name', 'LIKE', "%{$query}%")
                                ->orWhere('phone', 'LIKE', "%{$query}%");
                        });
                })
                ->when($this->currentTenant, function ($q) {
                    $q->where('tenant_id', $this->currentTenant->id);
                })
                ->orderBy('created_at', 'desc')
                ->limit($limit)
                ->get();

            $response = [
                'success' => true,
                'data' => [
                    'query' => $query,
                    'results' => $vouchers->map(function ($voucher) {
                        return [
                            'id' => $voucher->id,
                            'code' => $voucher->code,
                            'profile' => $voucher->profile,
                            'status' => $voucher->status,
                            'expires_at' => $voucher->expires_at?->toISOString(),
                            'remaining_hours' => $voucher->getRemainingHoursAttribute(),
                            'customer' => $voucher->customer ? [
                                'name' => $voucher->customer->name,
                                'phone' => $voucher->customer->phone,
                            ] : null,
                        ];
                    })->toArray(),
                    'count' => $vouchers->count(),
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

        } catch (\Exception $e) {
            Log::channel('voucher')->error('Voucher search failed', [
                'query' => $request->get('query'),
                'tenant_id' => $this->currentTenant?->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Search failed',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Resend voucher SMS
     */
    public function resendSms(string $code): JsonResponse
    {
        try {
            // Verify voucher exists and belongs to tenant if applicable
            $query = Voucher::with(['customer'])
                ->where('code', $code);

            if ($this->currentTenant) {
                $query->where('tenant_id', $this->currentTenant->id);
            }

            $voucher = $query->firstOrFail();

            // Check if voucher has a customer with phone
            if (!$voucher->customer || !$voucher->customer->phone) {
                throw new \Exception('No customer phone available for this voucher');
            }

            // Send SMS using the same method as in VoucherService
            $smsService = app(\App\Services\SmsService::class);
            $smsSent = $smsService->sendVoucher($voucher->customer->phone, $voucher);

            if ($smsSent) {
                // Update SMS sent timestamp
                $voucher->update(['sms_sent_at' => now()]);

                Log::channel('voucher')->info('Voucher SMS resent', [
                    'voucher_id' => $voucher->id,
                    'code' => $code,
                    'phone' => $voucher->customer->phone,
                    'tenant_id' => $this->currentTenant?->id,
                    'resent_by' => auth()->id() ?? 'system',
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'SMS resent successfully',
                    'data' => [
                        'code' => $code,
                        'phone' => $voucher->customer->phone,
                        'sms_sent_at' => now()->toISOString(),
                    ]
                ]);
            } else {
                throw new \Exception('Failed to send SMS');
            }

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Voucher not found'
            ], 404);

        } catch (\Exception $e) {
            Log::channel('voucher')->error('Failed to resend voucher SMS', [
                'code' => $code,
                'tenant_id' => $this->currentTenant?->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to resend SMS: ' . $e->getMessage(),
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }
}
