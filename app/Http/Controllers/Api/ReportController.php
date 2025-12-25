<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\Payment;
use App\Models\Voucher;
use App\Models\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

class ReportController extends Controller
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
     * Get payments report
     */
    public function payments(Request $request): JsonResponse
    {
        try {
            // Validate request parameters
            $validator = \Validator::make($request->all(), [
                'period' => 'in:daily,weekly,monthly,yearly,custom',
                'start_date' => 'date_format:Y-m-d',
                'end_date' => 'date_format:Y-m-d',
                'group_by' => 'in:day,week,month,year,package,status,payment_method',
                'export' => 'in:csv,json',
                'limit' => 'integer|min:1|max:1000',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $period = $request->get('period', 'monthly');
            $groupBy = $request->get('group_by', 'day');
            $limit = $request->get('limit', 100);
            $export = $request->get('export');

            // Build query
            $query = Payment::query();

            // Scope by tenant if current tenant exists
            if ($this->currentTenant) {
                $query->where('tenant_id', $this->currentTenant->id);
            }

            // Apply date filters
            $dateRange = $this->getDateRange($period, $request);
            if ($dateRange['start']) {
                $query->where('created_at', '>=', $dateRange['start']);
            }
            if ($dateRange['end']) {
                $query->where('created_at', '<=', $dateRange['end']);
            }

            // Apply additional filters
            if ($request->has('status')) {
                $query->where('status', $request->get('status'));
            }

            if ($request->has('package')) {
                $query->whereJsonContains('metadata->package', $request->get('package'));
            }

            if ($request->has('min_amount')) {
                $query->where('amount', '>=', $request->get('min_amount'));
            }

            if ($request->has('max_amount')) {
                $query->where('amount', '<=', $request->get('max_amount'));
            }

            if ($request->has('payment_method')) {
                $query->where('payment_method', $request->get('payment_method'));
            }

            // Get total statistics
            $totalStats = $this->getPaymentStatistics($query);

            // Group data based on group_by parameter
            $groupedData = $this->groupPaymentData($query, $groupBy, $limit);

            // Prepare response
            $response = [
                'success' => true,
                'data' => [
                    'summary' => [
                        'period' => $period,
                        'start_date' => $dateRange['start']?->format('Y-m-d'),
                        'end_date' => $dateRange['end']?->format('Y-m-d'),
                        'total_records' => $totalStats['total_records'],
                        'date_range' => $dateRange,
                    ],
                    'statistics' => $totalStats,
                    'grouped_data' => $groupedData,
                    'trends' => $this->getPaymentTrends($query, $groupBy),
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

            // Handle export if requested
            if ($export === 'csv') {
                return $this->exportPaymentsToCsv($query, $period);
            }

            return response()->json($response);

        } catch (\Exception $e) {
            Log::channel('report')->error('Failed to generate payments report', [
                'tenant_id' => $this->currentTenant?->id,
                'error' => $e->getMessage(),
                'params' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to generate payments report',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Get vouchers report
     */
    public function vouchers(Request $request): JsonResponse
    {
        try {
            // Validate request parameters
            $validator = \Validator::make($request->all(), [
                'period' => 'in:daily,weekly,monthly,yearly,custom',
                'start_date' => 'date_format:Y-m-d',
                'end_date' => 'date_format:Y-m-d',
                'group_by' => 'in:day,week,month,year,profile,status',
                'export' => 'in:csv,json',
                'limit' => 'integer|min:1|max:1000',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $period = $request->get('period', 'monthly');
            $groupBy = $request->get('group_by', 'day');
            $limit = $request->get('limit', 100);
            $export = $request->get('export');

            // Build query
            $query = Voucher::query();

            // Scope by tenant if current tenant exists
            if ($this->currentTenant) {
                $query->where('tenant_id', $this->currentTenant->id);
            }

            // Apply date filters
            $dateRange = $this->getDateRange($period, $request);
            if ($dateRange['start']) {
                $query->where('created_at', '>=', $dateRange['start']);
            }
            if ($dateRange['end']) {
                $query->where('created_at', '<=', $dateRange['end']);
            }

            // Apply additional filters
            if ($request->has('status')) {
                $query->where('status', $request->get('status'));
            }

            if ($request->has('profile')) {
                $query->where('profile', $request->get('profile'));
            }

            if ($request->has('customer_id')) {
                $query->where('customer_id', $request->get('customer_id'));
            }

            if ($request->has('has_payment')) {
                if ($request->get('has_payment') === 'true') {
                    $query->has('payment');
                } else {
                    $query->doesntHave('payment');
                }
            }

            // Get total statistics
            $totalStats = $this->getVoucherStatistics($query);

            // Group data based on group_by parameter
            $groupedData = $this->groupVoucherData($query, $groupBy, $limit);

            // Prepare response
            $response = [
                'success' => true,
                'data' => [
                    'summary' => [
                        'period' => $period,
                        'start_date' => $dateRange['start']?->format('Y-m-d'),
                        'end_date' => $dateRange['end']?->format('Y-m-d'),
                        'total_records' => $totalStats['total_records'],
                        'date_range' => $dateRange,
                    ],
                    'statistics' => $totalStats,
                    'grouped_data' => $groupedData,
                    'trends' => $this->getVoucherTrends($query, $groupBy),
                    'top_customers' => $this->getTopVoucherCustomers($query, 10),
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

            // Handle export if requested
            if ($export === 'csv') {
                return $this->exportVouchersToCsv($query, $period);
            }

            return response()->json($response);

        } catch (\Exception $e) {
            Log::channel('report')->error('Failed to generate vouchers report', [
                'tenant_id' => $this->currentTenant?->id,
                'error' => $e->getMessage(),
                'params' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to generate vouchers report',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Get customers report
     */
    public function customers(Request $request): JsonResponse
    {
        try {
            // Validate request parameters
            $validator = \Validator::make($request->all(), [
                'period' => 'in:daily,weekly,monthly,yearly,custom',
                'start_date' => 'date_format:Y-m-d',
                'end_date' => 'date_format:Y-m-d',
                'group_by' => 'in:day,week,month,year,registration_source',
                'export' => 'in:csv,json',
                'limit' => 'integer|min:1|max:1000',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $period = $request->get('period', 'monthly');
            $groupBy = $request->get('group_by', 'day');
            $limit = $request->get('limit', 100);
            $export = $request->get('export');

            // Build query
            $query = Customer::query();

            // Scope by tenant if current tenant exists
            if ($this->currentTenant) {
                $query->where('tenant_id', $this->currentTenant->id);
            }

            // Apply date filters
            $dateRange = $this->getDateRange($period, $request);
            if ($dateRange['start']) {
                $query->where('created_at', '>=', $dateRange['start']);
            }
            if ($dateRange['end']) {
                $query->where('created_at', '<=', $dateRange['end']);
            }

            // Apply additional filters
            if ($request->has('is_active')) {
                $query->where('is_active', $request->get('is_active') === 'true');
            }

            if ($request->has('registration_source')) {
                $query->whereJsonContains('metadata->registration_source', $request->get('registration_source'));
            }

            if ($request->has('has_payments')) {
                if ($request->get('has_payments') === 'true') {
                    $query->has('payments');
                } else {
                    $query->doesntHave('payments');
                }
            }

            if ($request->has('min_payments')) {
                $query->has('payments', '>=', $request->get('min_payments'));
            }

            // Get total statistics
            $totalStats = $this->getCustomerStatistics($query);

            // Group data based on group_by parameter
            $groupedData = $this->groupCustomerData($query, $groupBy, $limit);

            // Prepare response
            $response = [
                'success' => true,
                'data' => [
                    'summary' => [
                        'period' => $period,
                        'start_date' => $dateRange['start']?->format('Y-m-d'),
                        'end_date' => $dateRange['end']?->format('Y-m-d'),
                        'total_records' => $totalStats['total_customers'],
                        'date_range' => $dateRange,
                    ],
                    'statistics' => $totalStats,
                    'grouped_data' => $groupedData,
                    'trends' => $this->getCustomerTrends($query, $groupBy),
                    'top_customers' => $this->getTopSpendingCustomers($query, 10),
                    'customer_segments' => $this->getCustomerSegments($query),
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

            // Handle export if requested
            if ($export === 'csv') {
                return $this->exportCustomersToCsv($query, $period);
            }

            return response()->json($response);

        } catch (\Exception $e) {
            Log::channel('report')->error('Failed to generate customers report', [
                'tenant_id' => $this->currentTenant?->id,
                'error' => $e->getMessage(),
                'params' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to generate customers report',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Get revenue report
     */
    public function revenue(Request $request): JsonResponse
    {
        try {
            // Validate request parameters
            $validator = \Validator::make($request->all(), [
                'period' => 'in:daily,weekly,monthly,yearly,custom',
                'start_date' => 'date_format:Y-m-d',
                'end_date' => 'date_format:Y-m-d',
                'group_by' => 'in:day,week,month,year,package,payment_method',
                'export' => 'in:csv,json',
                'breakdown' => 'in:detailed,summary',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $period = $request->get('period', 'monthly');
            $groupBy = $request->get('group_by', 'day');
            $export = $request->get('export');
            $breakdown = $request->get('breakdown', 'summary');

            // Build query for completed payments only
            $query = Payment::where('status', 'completed');

            // Scope by tenant if current tenant exists
            if ($this->currentTenant) {
                $query->where('tenant_id', $this->currentTenant->id);
            }

            // Apply date filters
            $dateRange = $this->getDateRange($period, $request);
            if ($dateRange['start']) {
                $query->where('created_at', '>=', $dateRange['start']);
            }
            if ($dateRange['end']) {
                $query->where('created_at', '<=', $dateRange['end']);
            }

            // Apply additional filters
            if ($request->has('package')) {
                $query->whereJsonContains('metadata->package', $request->get('package'));
            }

            if ($request->has('payment_method')) {
                $query->where('payment_method', $request->get('payment_method'));
            }

            // Get revenue statistics
            $revenueStats = $this->getRevenueStatistics($query, $breakdown);

            // Get revenue trends
            $revenueTrends = $this->getRevenueTrends($query, $groupBy);

            // Get comparison data (vs previous period)
            $comparisonData = $this->getRevenueComparison($query, $period, $dateRange);

            // Prepare response
            $response = [
                'success' => true,
                'data' => [
                    'summary' => [
                        'period' => $period,
                        'start_date' => $dateRange['start']?->format('Y-m-d'),
                        'end_date' => $dateRange['end']?->format('Y-m-d'),
                        'date_range' => $dateRange,
                    ],
                    'statistics' => $revenueStats,
                    'trends' => $revenueTrends,
                    'comparison' => $comparisonData,
                    'forecast' => $this->getRevenueForecast($revenueTrends),
                    'breakdown' => $breakdown === 'detailed' ? $this->getDetailedRevenueBreakdown($query) : null,
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

            // Handle export if requested
            if ($export === 'csv') {
                return $this->exportRevenueToCsv($query, $period);
            }

            return response()->json($response);

        } catch (\Exception $e) {
            Log::channel('report')->error('Failed to generate revenue report', [
                'tenant_id' => $this->currentTenant?->id,
                'error' => $e->getMessage(),
                'params' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to generate revenue report',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Get usage report (voucher usage statistics)
     */
    public function usage(Request $request): JsonResponse
    {
        try {
            // Validate request parameters
            $validator = \Validator::make($request->all(), [
                'period' => 'in:daily,weekly,monthly,yearly,custom',
                'start_date' => 'date_format:Y-m-d',
                'end_date' => 'date_format:Y-m-d',
                'group_by' => 'in:day,week,month,year,profile',
                'export' => 'in:csv,json',
                'limit' => 'integer|min:1|max:1000',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $period = $request->get('period', 'monthly');
            $groupBy = $request->get('group_by', 'day');
            $limit = $request->get('limit', 100);
            $export = $request->get('export');

            // Build query for active vouchers
            $query = Voucher::query();

            // Scope by tenant if current tenant exists
            if ($this->currentTenant) {
                $query->where('tenant_id', $this->currentTenant->id);
            }

            // Apply date filters
            $dateRange = $this->getDateRange($period, $request);
            if ($dateRange['start']) {
                $query->where('created_at', '>=', $dateRange['start']);
            }
            if ($dateRange['end']) {
                $query->where('created_at', '<=', $dateRange['end']);
            }

            // Apply additional filters
            if ($request->has('profile')) {
                $query->where('profile', $request->get('profile'));
            }

            if ($request->has('status')) {
                $query->where('status', $request->get('status'));
            }

            // Get usage statistics
            $usageStats = $this->getUsageStatistics($query);

            // Get usage trends
            $usageTrends = $this->getUsageTrends($query, $groupBy);

            // Get profile usage breakdown
            $profileBreakdown = $this->getProfileUsageBreakdown($query);

            // Get peak usage times
            $peakUsage = $this->getPeakUsageTimes($query);

            // Prepare response
            $response = [
                'success' => true,
                'data' => [
                    'summary' => [
                        'period' => $period,
                        'start_date' => $dateRange['start']?->format('Y-m-d'),
                        'end_date' => $dateRange['end']?->format('Y-m-d'),
                        'date_range' => $dateRange,
                    ],
                    'statistics' => $usageStats,
                    'trends' => $usageTrends,
                    'profile_breakdown' => $profileBreakdown,
                    'peak_usage' => $peakUsage,
                    'utilization_rate' => $this->calculateUtilizationRate($query),
                    'recommendations' => $this->getUsageRecommendations($usageStats, $profileBreakdown),
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

            // Handle export if requested
            if ($export === 'csv') {
                return $this->exportUsageToCsv($query, $period);
            }

            return response()->json($response);

        } catch (\Exception $e) {
            Log::channel('report')->error('Failed to generate usage report', [
                'tenant_id' => $this->currentTenant?->id,
                'error' => $e->getMessage(),
                'params' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to generate usage report',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Helper: Get date range based on period
     */
    private function getDateRange(string $period, Request $request): array
    {
        $startDate = null;
        $endDate = null;

        switch ($period) {
            case 'daily':
                $startDate = Carbon::today();
                $endDate = Carbon::today()->endOfDay();
                break;

            case 'weekly':
                $startDate = Carbon::now()->startOfWeek();
                $endDate = Carbon::now()->endOfWeek();
                break;

            case 'monthly':
                $startDate = Carbon::now()->startOfMonth();
                $endDate = Carbon::now()->endOfMonth();
                break;

            case 'yearly':
                $startDate = Carbon::now()->startOfYear();
                $endDate = Carbon::now()->endOfYear();
                break;

            case 'custom':
                if ($request->has('start_date')) {
                    $startDate = Carbon::parse($request->get('start_date'))->startOfDay();
                }
                if ($request->has('end_date')) {
                    $endDate = Carbon::parse($request->get('end_date'))->endOfDay();
                }
                break;
        }

        return [
            'start' => $startDate,
            'end' => $endDate,
        ];
    }

    /**
     * Helper: Get payment statistics
     */
    private function getPaymentStatistics($query): array
    {
        $totalRecords = $query->count();

        $totalAmount = $query->sum('amount');
        $avgAmount = $totalRecords > 0 ? round($totalAmount / $totalRecords, 2) : 0;

        $statusCounts = $query->select('status', DB::raw('count(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        $packageBreakdown = $query->selectRaw("metadata->>'package' as package, count(*) as count, sum(amount) as revenue")
            ->groupByRaw("metadata->>'package'")
            ->orderByDesc('revenue')
            ->get()
            ->toArray();

        $paymentMethodBreakdown = $query->select('payment_method', DB::raw('count(*) as count, sum(amount) as revenue'))
            ->groupBy('payment_method')
            ->orderByDesc('revenue')
            ->get()
            ->toArray();

        $successRate = isset($statusCounts['completed'])
            ? round(($statusCounts['completed'] / $totalRecords) * 100, 2)
            : 0;

        return [
            'total_records' => $totalRecords,
            'total_amount' => $totalAmount,
            'average_amount' => $avgAmount,
            'status_distribution' => $statusCounts,
            'package_breakdown' => $packageBreakdown,
            'payment_method_breakdown' => $paymentMethodBreakdown,
            'success_rate' => $successRate,
            'min_amount' => $query->min('amount') ?? 0,
            'max_amount' => $query->max('amount') ?? 0,
        ];
    }

    /**
     * Helper: Group payment data
     */
    private function groupPaymentData($query, string $groupBy, int $limit): array
    {
        switch ($groupBy) {
            case 'day':
                $grouped = $query->selectRaw("
                    DATE(created_at) as date,
                    COUNT(*) as count,
                    SUM(amount) as revenue,
                    AVG(amount) as avg_amount,
                    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_count
                ")
                    ->groupBy('date')
                    ->orderByDesc('date')
                    ->limit($limit)
                    ->get();
                break;

            case 'week':
                $grouped = $query->selectRaw("
                    YEAR(created_at) as year,
                    WEEK(created_at) as week,
                    CONCAT(YEAR(created_at), '-W', LPAD(WEEK(created_at), 2, '0')) as period,
                    COUNT(*) as count,
                    SUM(amount) as revenue,
                    AVG(amount) as avg_amount
                ")
                    ->groupBy('year', 'week')
                    ->orderByDesc('year')
                    ->orderByDesc('week')
                    ->limit($limit)
                    ->get();
                break;

            case 'month':
                $grouped = $query->selectRaw("
                    YEAR(created_at) as year,
                    MONTH(created_at) as month,
                    DATE_FORMAT(created_at, '%Y-%m') as period,
                    COUNT(*) as count,
                    SUM(amount) as revenue,
                    AVG(amount) as avg_amount
                ")
                    ->groupBy('year', 'month')
                    ->orderByDesc('year')
                    ->orderByDesc('month')
                    ->limit($limit)
                    ->get();
                break;

            case 'year':
                $grouped = $query->selectRaw("
                    YEAR(created_at) as year,
                    COUNT(*) as count,
                    SUM(amount) as revenue,
                    AVG(amount) as avg_amount
                ")
                    ->groupBy('year')
                    ->orderByDesc('year')
                    ->limit($limit)
                    ->get();
                break;

            case 'package':
                $grouped = $query->selectRaw("
                    metadata->>'package' as package,
                    COUNT(*) as count,
                    SUM(amount) as revenue,
                    AVG(amount) as avg_amount,
                    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_count
                ")
                    ->groupByRaw("metadata->>'package'")
                    ->orderByDesc('revenue')
                    ->limit($limit)
                    ->get();
                break;

            case 'status':
                $grouped = $query->selectRaw("
                    status,
                    COUNT(*) as count,
                    SUM(amount) as revenue,
                    AVG(amount) as avg_amount
                ")
                    ->groupBy('status')
                    ->orderByDesc('count')
                    ->limit($limit)
                    ->get();
                break;

            case 'payment_method':
                $grouped = $query->selectRaw("
                    payment_method,
                    COUNT(*) as count,
                    SUM(amount) as revenue,
                    AVG(amount) as avg_amount
                ")
                    ->groupBy('payment_method')
                    ->orderByDesc('revenue')
                    ->limit($limit)
                    ->get();
                break;

            default:
                $grouped = collect();
        }

        return $grouped->toArray();
    }

    /**
     * Helper: Get payment trends
     */
    private function getPaymentTrends($query, string $groupBy): array
    {
        // Get last 30 days of data for trend analysis
        $thirtyDaysAgo = Carbon::now()->subDays(30);
        $trendQuery = clone $query;
        $trendQuery->where('created_at', '>=', $thirtyDaysAgo);

        switch ($groupBy) {
            case 'day':
                $trends = $trendQuery->selectRaw("
                    DATE(created_at) as date,
                    COUNT(*) as daily_count,
                    SUM(amount) as daily_revenue
                ")
                    ->groupBy('date')
                    ->orderBy('date')
                    ->get();
                break;

            case 'week':
                $trends = $trendQuery->selectRaw("
                    YEAR(created_at) as year,
                    WEEK(created_at) as week,
                    COUNT(*) as weekly_count,
                    SUM(amount) as weekly_revenue
                ")
                    ->groupBy('year', 'week')
                    ->orderBy('year')
                    ->orderBy('week')
                    ->get();
                break;

            default:
                $trends = collect();
        }

        return [
            'data' => $trends->toArray(),
            'period' => '30_days',
            'total_days' => $trends->count(),
            'growth_rate' => $this->calculateGrowthRate($trends, 'daily_revenue' ?? 'weekly_revenue'),
        ];
    }

    /**
     * Helper: Get voucher statistics
     */
    private function getVoucherStatistics($query): array
    {
        $totalRecords = $query->count();

        $totalValue = $query->sum('price');
        $avgValue = $totalRecords > 0 ? round($totalValue / $totalRecords, 2) : 0;

        $statusCounts = $query->select('status', DB::raw('count(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        $profileBreakdown = $query->select('profile', DB::raw('count(*) as count, sum(price) as total_value'))
            ->groupBy('profile')
            ->orderByDesc('count')
            ->get()
            ->toArray();

        $expiredCount = $query->where('expires_at', '<', now())->count();
        $expirationRate = $totalRecords > 0 ? round(($expiredCount / $totalRecords) * 100, 2) : 0;

        $avgValidityHours = $query->avg('validity_hours') ?? 0;

        return [
            'total_records' => $totalRecords,
            'total_value' => $totalValue,
            'average_value' => $avgValue,
            'status_distribution' => $statusCounts,
            'profile_breakdown' => $profileBreakdown,
            'expired_count' => $expiredCount,
            'expiration_rate' => $expirationRate,
            'average_validity_hours' => round($avgValidityHours, 2),
            'active_count' => $query->where('status', 'active')->count(),
            'with_payment_count' => $query->has('payment')->count(),
            'without_payment_count' => $query->doesntHave('payment')->count(),
        ];
    }

    /**
     * Helper: Group voucher data
     */
    private function groupVoucherData($query, string $groupBy, int $limit): array
    {
        // Similar to groupPaymentData but for vouchers
        // Implementation would follow the same pattern
        return [];
    }

    /**
     * Helper: Get voucher trends
     */
    private function getVoucherTrends($query, string $groupBy): array
    {
        // Similar to getPaymentTrends but for vouchers
        // Implementation would follow the same pattern
        return [];
    }

    /**
     * Helper: Get customer statistics
     */
    private function getCustomerStatistics($query): array
    {
        $totalCustomers = $query->count();

        $activeCustomers = $query->where('is_active', true)->count();
        $inactiveCustomers = $query->where('is_active', false)->count();

        $withPayments = $query->has('payments')->count();
        $withoutPayments = $query->doesntHave('payments')->count();

        $registrationSources = $query->selectRaw("metadata->>'registration_source' as source, COUNT(*) as count")
            ->whereNotNull('metadata->registration_source')
            ->groupByRaw("metadata->>'registration_source'")
            ->orderByDesc('count')
            ->get()
            ->toArray();

        $avgPaymentsPerCustomer = $withPayments > 0
            ? round($query->withCount('payments')->avg('payments_count') ?? 0, 2)
            : 0;

        $avgVouchersPerCustomer = $query->withCount('vouchers')->avg('vouchers_count') ?? 0;

        return [
            'total_customers' => $totalCustomers,
            'active_customers' => $activeCustomers,
            'inactive_customers' => $inactiveCustomers,
            'customers_with_payments' => $withPayments,
            'customers_without_payments' => $withoutPayments,
            'conversion_rate' => $totalCustomers > 0 ? round(($withPayments / $totalCustomers) * 100, 2) : 0,
            'registration_sources' => $registrationSources,
            'average_payments_per_customer' => $avgPaymentsPerCustomer,
            'average_vouchers_per_customer' => round($avgVouchersPerCustomer, 2),
        ];
    }

    /**
     * Helper: Group customer data
     */
    private function groupCustomerData($query, string $groupBy, int $limit): array
    {
        // Similar to groupPaymentData but for customers
        // Implementation would follow the same pattern
        return [];
    }

    /**
     * Helper: Get customer trends
     */
    private function getCustomerTrends($query, string $groupBy): array
    {
        // Similar to getPaymentTrends but for customers
        // Implementation would follow the same pattern
        return [];
    }

    /**
     * Helper: Get revenue statistics
     */
    private function getRevenueStatistics($query, string $breakdown): array
    {
        $totalRevenue = $query->sum('amount');
        $totalTransactions = $query->count();
        $avgTransactionValue = $totalTransactions > 0 ? round($totalRevenue / $totalTransactions, 2) : 0;

        $stats = [
            'total_revenue' => $totalRevenue,
            'total_transactions' => $totalTransactions,
            'average_transaction_value' => $avgTransactionValue,
            'daily_average' => $this->calculateDailyAverage($query),
            'weekly_average' => $this->calculateWeeklyAverage($query),
            'monthly_average' => $this->calculateMonthlyAverage($query),
        ];

        if ($breakdown === 'detailed') {
            $stats['hourly_distribution'] = $this->getHourlyRevenueDistribution($query);
            $stats['weekday_distribution'] = $this->getWeekdayRevenueDistribution($query);
            $stats['customer_segments'] = $this->getRevenueByCustomerSegment($query);
        }

        return $stats;
    }

    /**
     * Helper: Get revenue trends
     */
    private function getRevenueTrends($query, string $groupBy): array
    {
        $trendQuery = clone $query;

        switch ($groupBy) {
            case 'day':
                $trends = $trendQuery->selectRaw("
                    DATE(created_at) as date,
                    SUM(amount) as revenue,
                    COUNT(*) as transactions,
                    AVG(amount) as avg_value
                ")
                    ->groupBy('date')
                    ->orderBy('date')
                    ->get();
                break;

            case 'week':
                $trends = $trendQuery->selectRaw("
                    YEAR(created_at) as year,
                    WEEK(created_at) as week,
                    SUM(amount) as revenue,
                    COUNT(*) as transactions,
                    AVG(amount) as avg_value
                ")
                    ->groupBy('year', 'week')
                    ->orderBy('year')
                    ->orderBy('week')
                    ->get();
                break;

            case 'month':
                $trends = $trendQuery->selectRaw("
                    YEAR(created_at) as year,
                    MONTH(created_at) as month,
                    SUM(amount) as revenue,
                    COUNT(*) as transactions,
                    AVG(amount) as avg_value
                ")
                    ->groupBy('year', 'month')
                    ->orderBy('year')
                    ->orderBy('month')
                    ->get();
                break;

            default:
                $trends = collect();
        }

        return [
            'data' => $trends->toArray(),
            'growth_rate' => $this->calculateGrowthRate($trends, 'revenue'),
            'seasonality' => $this->analyzeSeasonality($trends),
        ];
    }

    /**
     * Helper: Get usage statistics
     */
    private function getUsageStatistics($query): array
    {
        $totalVouchers = $query->count();
        $activeVouchers = $query->where('status', 'active')->count();
        $usedVouchers = $query->where('status', 'used')->count();
        $expiredVouchers = $query->expired()->count();

        $avgValidityHours = $query->avg('validity_hours') ?? 0;
        $avgRemainingHours = $query->active()->avg(
            DB::raw("TIMESTAMPDIFF(HOUR, NOW(), expires_at)")
        ) ?? 0;

        $utilizationRate = $totalVouchers > 0
            ? round((($activeVouchers + $usedVouchers) / $totalVouchers) * 100, 2)
            : 0;

        return [
            'total_vouchers' => $totalVouchers,
            'active_vouchers' => $activeVouchers,
            'used_vouchers' => $usedVouchers,
            'expired_vouchers' => $expiredVouchers,
            'average_validity_hours' => round($avgValidityHours, 2),
            'average_remaining_hours' => round($avgRemainingHours, 2),
            'utilization_rate' => $utilizationRate,
            'expiration_rate' => $totalVouchers > 0 ? round(($expiredVouchers / $totalVouchers) * 100, 2) : 0,
        ];
    }

    /**
     * Helper: Get usage trends
     */
    private function getUsageTrends($query, string $groupBy): array
    {
        // Similar to getRevenueTrends but for usage
        // Implementation would follow the same pattern
        return [];
    }

    /**
     * Helper: Calculate growth rate
     */
    private function calculateGrowthRate($data, string $valueField): float
    {
        if ($data->count() < 2) {
            return 0;
        }

        $firstValue = $data->first()[$valueField] ?? 0;
        $lastValue = $data->last()[$valueField] ?? 0;

        if ($firstValue == 0) {
            return 100;
        }

        return round((($lastValue - $firstValue) / $firstValue) * 100, 2);
    }

    /**
     * Helper: Export payments to CSV
     */
    private function exportPaymentsToCsv($query, string $period): JsonResponse
    {
        $payments = $query->with(['customer'])->get();

        $csvData = "Transaction ID,Amount,Currency,Status,Customer Name,Customer Phone,Package,Payment Method,Created At,Paid At,Tenant\n";

        foreach ($payments as $payment) {
            $csvData .= sprintf(
                "%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s\n",
                $payment->transaction_id,
                $payment->amount,
                $payment->currency,
                $payment->status,
                $payment->customer?->name ?? 'N/A',
                $payment->customer?->phone ?? 'N/A',
                $payment->metadata['package'] ?? 'N/A',
                $payment->payment_method,
                $payment->created_at->format('Y-m-d H:i:s'),
                $payment->paid_at?->format('Y-m-d H:i:s') ?? 'N/A',
                $payment->tenant_id ? 'Tenant-' . $payment->tenant_id : 'Global'
            );
        }

        $filename = 'payments_report_' . ($this->currentTenant ? $this->currentTenant->code . '_' : '') . $period . '_' . now()->format('Y-m-d') . '.csv';

        return response()->streamDownload(function () use ($csvData) {
            echo $csvData;
        }, $filename, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    /**
     * Helper: Export vouchers to CSV
     */
    private function exportVouchersToCsv($query, string $period): JsonResponse
    {
        $vouchers = $query->with(['customer', 'payment'])->get();

        $csvData = "Code,Profile,Status,Validity Hours,Data Limit,Price,Currency,Customer Name,Customer Phone,Activated At,Expires At,Created At,Tenant\n";

        foreach ($vouchers as $voucher) {
            $csvData .= sprintf(
                "%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s\n",
                $voucher->code,
                $voucher->profile,
                $voucher->status,
                $voucher->validity_hours,
                $voucher->data_limit_mb ?? 'Unlimited',
                $voucher->price ?? '0.00',
                $voucher->currency,
                $voucher->customer?->name ?? 'N/A',
                $voucher->customer?->phone ?? 'N/A',
                $voucher->activated_at?->format('Y-m-d H:i:s') ?? 'N/A',
                $voucher->expires_at?->format('Y-m-d H:i:s') ?? 'N/A',
                $voucher->created_at->format('Y-m-d H:i:s'),
                $voucher->tenant_id ? 'Tenant-' . $voucher->tenant_id : 'Global'
            );
        }

        $filename = 'vouchers_report_' . ($this->currentTenant ? $this->currentTenant->code . '_' : '') . $period . '_' . now()->format('Y-m-d') . '.csv';

        return response()->streamDownload(function () use ($csvData) {
            echo $csvData;
        }, $filename, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    /**
     * Helper: Export customers to CSV
     */
    private function exportCustomersToCsv($query, string $period): JsonResponse
    {
        $customers = $query->withCount(['payments', 'vouchers'])->get();

        $csvData = "Name,Phone,Email,Status,Total Payments,Total Vouchers,First Payment,Last Payment,Created At,Tenant\n";

        foreach ($customers as $customer) {
            $firstPayment = $customer->payments()->orderBy('created_at')->first();
            $lastPayment = $customer->payments()->orderByDesc('created_at')->first();

            $csvData .= sprintf(
                "%s,%s,%s,%s,%s,%s,%s,%s,%s,%s\n",
                $customer->name,
                $customer->phone,
                $customer->email ?? 'N/A',
                $customer->is_active ? 'Active' : 'Inactive',
                $customer->payments_count,
                $customer->vouchers_count,
                $firstPayment?->created_at?->format('Y-m-d') ?? 'N/A',
                $lastPayment?->created_at?->format('Y-m-d') ?? 'N/A',
                $customer->created_at->format('Y-m-d H:i:s'),
                $customer->tenant_id ? 'Tenant-' . $customer->tenant_id : 'Global'
            );
        }

        $filename = 'customers_report_' . ($this->currentTenant ? $this->currentTenant->code . '_' : '') . $period . '_' . now()->format('Y-m-d') . '.csv';

        return response()->streamDownload(function () use ($csvData) {
            echo $csvData;
        }, $filename, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    /**
     * Helper: Export revenue to CSV
     */
    private function exportRevenueToCsv($query, string $period): JsonResponse
    {
        $revenueData = $query->selectRaw("
            DATE(created_at) as date,
            COUNT(*) as transactions,
            SUM(amount) as revenue,
            AVG(amount) as avg_transaction
        ")
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        $csvData = "Date,Transactions,Revenue,Average Transaction,Tenant\n";

        foreach ($revenueData as $row) {
            $csvData .= sprintf(
                "%s,%s,%s,%s,%s\n",
                $row->date,
                $row->transactions,
                $row->revenue,
                round($row->avg_transaction, 2),
                $this->currentTenant ? 'Tenant-' . $this->currentTenant->code : 'Global'
            );
        }

        $filename = 'revenue_report_' . ($this->currentTenant ? $this->currentTenant->code . '_' : '') . $period . '_' . now()->format('Y-m-d') . '.csv';

        return response()->streamDownload(function () use ($csvData) {
            echo $csvData;
        }, $filename, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    /**
     * Helper: Export usage to CSV
     */
    private function exportUsageToCsv($query, string $period): JsonResponse
    {
        $usageData = $query->selectRaw("
            DATE(created_at) as date,
            COUNT(*) as vouchers_created,
            SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_vouchers,
            SUM(CASE WHEN expires_at < NOW() THEN 1 ELSE 0 END) as expired_vouchers,
            AVG(validity_hours) as avg_validity_hours
        ")
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        $csvData = "Date,Vouchers Created,Active Vouchers,Expired Vouchers,Average Validity (hours),Tenant\n";

        foreach ($usageData as $row) {
            $csvData .= sprintf(
                "%s,%s,%s,%s,%s,%s\n",
                $row->date,
                $row->vouchers_created,
                $row->active_vouchers,
                $row->expired_vouchers,
                round($row->avg_validity_hours, 2),
                $this->currentTenant ? 'Tenant-' . $this->currentTenant->code : 'Global'
            );
        }

        $filename = 'usage_report_' . ($this->currentTenant ? $this->currentTenant->code . '_' : '') . $period . '_' . now()->format('Y-m-d') . '.csv';

        return response()->streamDownload(function () use ($csvData) {
            echo $csvData;
        }, $filename, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    // Additional helper methods would be implemented here
    // such as getTopVoucherCustomers, getTopSpendingCustomers, getCustomerSegments,
    // getRevenueComparison, getRevenueForecast, getDetailedRevenueBreakdown,
    // getProfileUsageBreakdown, getPeakUsageTimes, calculateUtilizationRate,
    // getUsageRecommendations, and other statistical calculation methods
}
