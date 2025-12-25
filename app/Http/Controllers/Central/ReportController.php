<?php

namespace App\Http\Controllers\Central;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

class ReportController extends Controller
{
    /**
     * Get tenants report
     */
    public function tenants(Request $request): JsonResponse
    {
        try {
            $period = $request->input('period', 'month'); // day, week, month, year, all
            $groupBy = $request->input('group_by', 'plan'); // plan, status, billing_cycle
            $limit = $request->input('limit', 10);

            // Calculate date range
            $dateRange = $this->calculateDateRange($period);
            $startDate = $dateRange['start'];
            $endDate = $dateRange['end'];

            // Base query
            $query = Tenant::query();

            // Apply date filter
            if ($period !== 'all') {
                $query->whereBetween('created_at', [$startDate, $endDate]);
            }

            // Get summary statistics
            $summary = $this->getTenantsSummary($query, $period);

            // Get growth data
            $growthData = $this->getTenantsGrowthData($period, $startDate, $endDate);

            // Get grouping data
            $groupData = $this->getTenantsGroupData($query, $groupBy, $limit);

            // Get recent tenants
            $recentTenants = Tenant::with('domains')
                ->latest()
                ->take(5)
                ->get()
                ->map(function ($tenant) {
                    return [
                        'id' => $tenant->id,
                        'name' => $tenant->name,
                        'plan' => $tenant->plan,
                        'status' => $tenant->is_active ? 'active' : 'suspended',
                        'created_at' => $tenant->created_at,
                        'domains' => $tenant->domains->pluck('domain')->toArray(),
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => [
                    'report_type' => 'tenants',
                    'period' => $period,
                    'date_range' => [
                        'start' => $startDate->toISOString(),
                        'end' => $endDate->toISOString(),
                    ],
                    'summary' => $summary,
                    'growth' => $growthData,
                    'group_analysis' => [
                        'group_by' => $groupBy,
                        'data' => $groupData,
                    ],
                    'recent_activity' => $recentTenants,
                    'exportable' => $this->getTenantsExportData($query),
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate tenants report',
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
            $period = $request->input('period', 'month');
            $groupBy = $request->input('group_by', 'plan'); // plan, tenant, status
            $includeProjections = $request->boolean('include_projections', true);

            // Calculate date range
            $dateRange = $this->calculateDateRange($period);
            $startDate = $dateRange['start'];
            $endDate = $dateRange['end'];

            // Get revenue data from all tenants
            $revenueData = $this->getRevenueData($period, $startDate, $endDate);

            // Get recurring revenue metrics
            $recurringRevenue = $this->getRecurringRevenueMetrics();

            // Get top performing tenants
            $topTenants = $this->getTopRevenueTenants($startDate, $endDate, 10);

            // Get revenue by plan
            $revenueByPlan = $this->getRevenueByPlan($startDate, $endDate);

            // Get projections if requested
            $projections = $includeProjections ? $this->getRevenueProjections($period) : null;

            return response()->json([
                'success' => true,
                'data' => [
                    'report_type' => 'revenue',
                    'period' => $period,
                    'date_range' => [
                        'start' => $startDate->toISOString(),
                        'end' => $endDate->toISOString(),
                    ],
                    'summary' => [
                        'total_revenue' => $revenueData['total_revenue'],
                        'recurring_revenue' => $recurringRevenue['mrr'],
                        'average_revenue_per_tenant' => $revenueData['average_per_tenant'],
                        'revenue_growth' => $revenueData['growth_rate'],
                        'new_customers_revenue' => $revenueData['new_customers_revenue'],
                        'existing_customers_revenue' => $revenueData['existing_customers_revenue'],
                    ],
                    'breakdown' => [
                        'by_plan' => $revenueByPlan,
                        'by_tenant' => $topTenants,
                        'daily_trend' => $revenueData['daily_data'],
                    ],
                    'recurring_metrics' => $recurringRevenue,
                    'top_performers' => [
                        'tenants' => $topTenants->take(5),
                        'plans' => $revenueByPlan->sortByDesc('revenue')->take(3),
                    ],
                    'projections' => $projections,
                    'exportable' => $this->getRevenueExportData($revenueData, $recurringRevenue, $topTenants),
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate revenue report',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Get usage report
     */
    public function usage(Request $request): JsonResponse
    {
        try {
            $period = $request->input('period', 'week');
            $metric = $request->input('metric', 'all'); // users, vouchers, storage, api_calls
            $tenantId = $request->input('tenant_id');

            // Calculate date range
            $dateRange = $this->calculateDateRange($period);
            $startDate = $dateRange['start'];
            $endDate = $dateRange['end'];

            // Get overall usage statistics
            $overallUsage = $this->getOverallUsageStatistics($period);

            // Get usage trends
            $usageTrends = $this->getUsageTrends($period, $metric, $startDate, $endDate);

            // Get top usage by tenant
            $topUsageTenants = $this->getTopUsageTenants($metric, $startDate, $endDate, 10);

            // Get limit utilization
            $limitUtilization = $this->getLimitUtilization();

            // Get anomalies (over-usage)
            $anomalies = $this->getUsageAnomalies($startDate, $endDate);

            // Get tenant-specific usage if requested
            $tenantUsage = null;
            if ($tenantId) {
                $tenant = Tenant::find($tenantId);
                if ($tenant) {
                    $tenantUsage = $tenant->getUsageStatistics();
                }
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'report_type' => 'usage',
                    'period' => $period,
                    'metric' => $metric,
                    'date_range' => [
                        'start' => $startDate->toISOString(),
                        'end' => $endDate->toISOString(),
                    ],
                    'summary' => $overallUsage,
                    'trends' => $usageTrends,
                    'top_tenants' => $topUsageTenants,
                    'limit_utilization' => $limitUtilization,
                    'anomalies' => $anomalies,
                    'tenant_usage' => $tenantUsage,
                    'recommendations' => $this->getUsageRecommendations($overallUsage, $limitUtilization),
                    'exportable' => $this->getUsageExportData($overallUsage, $usageTrends, $topUsageTenants),
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate usage report',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Calculate date range based on period
     */
    private function calculateDateRange(string $period): array
    {
        $now = Carbon::now();

        return match($period) {
            'day' => [
                'start' => $now->copy()->startOfDay(),
                'end' => $now->copy()->endOfDay(),
            ],
            'week' => [
                'start' => $now->copy()->startOfWeek(),
                'end' => $now->copy()->endOfWeek(),
            ],
            'month' => [
                'start' => $now->copy()->startOfMonth(),
                'end' => $now->copy()->endOfMonth(),
            ],
            'quarter' => [
                'start' => $now->copy()->startOfQuarter(),
                'end' => $now->copy()->endOfQuarter(),
            ],
            'year' => [
                'start' => $now->copy()->startOfYear(),
                'end' => $now->copy()->endOfYear(),
            ],
            'custom' => [
                'start' => request()->input('start_date', $now->copy()->subMonth()),
                'end' => request()->input('end_date', $now),
            ],
            default => [ // 'all' or default
                'start' => Carbon::createFromDate(2020, 1, 1), // Adjust based on your business start
                'end' => $now,
            ],
        };
    }

    /**
     * Get tenants summary statistics
     */
    private function getTenantsSummary($query, string $period): array
    {
        $totalTenants = Tenant::count();
        $activeTenants = Tenant::active()->count();
        $newTenants = $period !== 'all' ? $query->count() : 0;
        $churnedTenants = $this->getChurnedTenantsCount($period);

        return [
            'total_tenants' => $totalTenants,
            'active_tenants' => $activeTenants,
            'suspended_tenants' => $totalTenants - $activeTenants,
            'new_tenants' => $newTenants,
            'churned_tenants' => $churnedTenants,
            'retention_rate' => $totalTenants > 0 ?
                (($totalTenants - $churnedTenants) / $totalTenants) * 100 : 100,
            'avg_tenants_per_day' => $this->getAverageTenantsPerDay($period),
        ];
    }

    /**
     * Get tenants growth data
     */
    private function getTenantsGrowthData(string $period, Carbon $startDate, Carbon $endDate): array
    {
        $interval = $this->getIntervalForPeriod($period);

        $growth = Tenant::selectRaw(
            "DATE_FORMAT(created_at, '{$interval}') as period,
            COUNT(*) as count,
            SUM(CASE WHEN is_active = true THEN 1 ELSE 0 END) as active_count"
        )
            ->whereBetween('created_at', [$startDate, $endDate])
            ->groupBy('period')
            ->orderBy('period')
            ->get();

        $cumulative = 0;
        $cumulativeData = $growth->map(function ($item) use (&$cumulative) {
            $cumulative += $item->count;
            return [
                'period' => $item->period,
                'new_tenants' => $item->count,
                'active_new' => $item->active_count,
                'cumulative_total' => $cumulative,
            ];
        });

        // Calculate growth rate
        $previousPeriodCount = Tenant::where('created_at', '<', $startDate)->count();
        $currentPeriodCount = $growth->sum('count');
        $growthRate = $previousPeriodCount > 0 ?
            (($currentPeriodCount) / $previousPeriodCount) * 100 :
            ($currentPeriodCount > 0 ? 100 : 0);

        return [
            'daily_growth' => $cumulativeData,
            'growth_rate' => round($growthRate, 2),
            'peak_day' => $growth->sortByDesc('count')->first(),
            'avg_daily_growth' => $growth->avg('count'),
        ];
    }

    /**
     * Get tenants grouped data
     */
    private function getTenantsGroupData($query, string $groupBy, int $limit): array
    {
        $grouping = match($groupBy) {
            'plan' => $query->selectRaw('plan, COUNT(*) as count, AVG(max_users) as avg_users')
                ->groupBy('plan')
                ->orderByDesc('count')
                ->get(),
            'status' => $query->selectRaw("
                CASE
                    WHEN is_active = true THEN 'active'
                    ELSE 'suspended'
                END as status,
                COUNT(*) as count
            ")
                ->groupBy('status')
                ->get(),
            'billing_cycle' => $query->selectRaw('billing_cycle, COUNT(*) as count')
                ->whereNotNull('billing_cycle')
                ->groupBy('billing_cycle')
                ->get(),
            'creation_month' => $query->selectRaw("
                DATE_FORMAT(created_at, '%Y-%m') as month,
                COUNT(*) as count
            ")
                ->groupBy('month')
                ->orderBy('month')
                ->get(),
            default => collect(),
        };

        return $grouping->map(function ($item) use ($groupBy) {
            $data = [
                'group' => $item->{$groupBy} ?? $item->{array_keys(get_object_vars($item))[0]},
                'count' => $item->count,
                'percentage' => 0, // Will be calculated
            ];

            if (isset($item->avg_users)) {
                $data['avg_users'] = round($item->avg_users, 2);
            }

            return $data;
        })->toArray();
    }

    /**
     * Get revenue data
     */
    private function getRevenueData(string $period, Carbon $startDate, Carbon $endDate): array
    {
        // This would typically query a payments table
        // For now, we'll simulate with tenant data

        $totalTenants = Tenant::whereBetween('created_at', [$startDate, $endDate])->count();
        $plans = ['basic' => 29, 'premium' => 79, 'enterprise' => 199, 'custom' => 299];

        $revenueByDay = Tenant::selectRaw(
            "DATE(created_at) as date,
            COUNT(*) as tenant_count,
            SUM(
                CASE
                    WHEN plan = 'basic' THEN 29
                    WHEN plan = 'premium' THEN 79
                    WHEN plan = 'enterprise' THEN 199
                    WHEN plan = 'custom' THEN 299
                    ELSE 0
                END
            ) as estimated_revenue"
        )
            ->whereBetween('created_at', [$startDate, $endDate])
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        $totalRevenue = $revenueByDay->sum('estimated_revenue');
        $averagePerTenant = $totalTenants > 0 ? $totalRevenue / $totalTenants : 0;

        // Calculate growth
        $previousPeriodStart = $startDate->copy()->sub($period);
        $previousPeriodEnd = $startDate->copy()->subDay();
        $previousRevenue = $this->getRevenueForPeriod($previousPeriodStart, $previousPeriodEnd);
        $growthRate = $previousRevenue > 0 ?
            (($totalRevenue - $previousRevenue) / $previousRevenue) * 100 :
            ($totalRevenue > 0 ? 100 : 0);

        return [
            'total_revenue' => $totalRevenue,
            'average_per_tenant' => round($averagePerTenant, 2),
            'growth_rate' => round($growthRate, 2),
            'daily_data' => $revenueByDay->pluck('estimated_revenue', 'date'),
            'new_customers_revenue' => $totalRevenue * 0.3, // Example: 30% from new customers
            'existing_customers_revenue' => $totalRevenue * 0.7, // Example: 70% from existing
        ];
    }

    /**
     * Get recurring revenue metrics
     */
    private function getRecurringRevenueMetrics(): array
    {
        $tenants = Tenant::active()->get();

        $mrr = $tenants->sum(function ($tenant) {
            $planPrices = [
                'basic' => 29,
                'premium' => 79,
                'enterprise' => 199,
                'custom' => 299,
            ];

            $monthlyRate = $planPrices[$tenant->plan] ?? 0;

            // Adjust for billing cycle
            return match($tenant->billing_cycle) {
                    'monthly' => $monthlyRate,
                    'quarterly' => $monthlyRate * 3,
                    'yearly' => $monthlyRate * 12,
                    default => $monthlyRate,
                } / 12; // Convert to monthly
        });

        $arr = $tenants->count() > 0 ? $mrr / $tenants->count() : 0;

        $churnRate = $this->calculateChurnRate();

        return [
            'mrr' => round($mrr, 2), // Monthly Recurring Revenue
            'arr' => round($arr * 12, 2), // Annual Recurring Revenue
            'average_mrr_per_tenant' => round($arr, 2),
            'churn_rate' => round($churnRate, 2),
            'lifetime_value' => round($this->calculateLTV(), 2),
        ];
    }

    /**
     * Get top revenue tenants
     */
    private function getTopRevenueTenants(Carbon $startDate, Carbon $endDate, int $limit)
    {
        return Tenant::withCount(['domains', 'users'])
            ->whereBetween('created_at', [$startDate, $endDate])
            ->get()
            ->map(function ($tenant) {
                $planPrices = [
                    'basic' => 29,
                    'premium' => 79,
                    'enterprise' => 199,
                    'custom' => 299,
                ];

                $revenue = $planPrices[$tenant->plan] ?? 0;

                return [
                    'id' => $tenant->id,
                    'name' => $tenant->name,
                    'plan' => $tenant->plan,
                    'revenue' => $revenue,
                    'user_count' => $tenant->users_count,
                    'domain_count' => $tenant->domains_count,
                    'status' => $tenant->is_active ? 'active' : 'suspended',
                ];
            })
            ->sortByDesc('revenue')
            ->take($limit)
            ->values();
    }

    /**
     * Get revenue by plan
     */
    private function getRevenueByPlan(Carbon $startDate, Carbon $endDate)
    {
        $planPrices = [
            'basic' => 29,
            'premium' => 79,
            'enterprise' => 199,
            'custom' => 299,
        ];

        return Tenant::selectRaw('plan, COUNT(*) as tenant_count')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->groupBy('plan')
            ->get()
            ->map(function ($item) use ($planPrices) {
                $revenue = ($planPrices[$item->plan] ?? 0) * $item->tenant_count;

                return [
                    'plan' => $item->plan,
                    'tenant_count' => $item->tenant_count,
                    'revenue' => $revenue,
                    'avg_revenue_per_tenant' => $planPrices[$item->plan] ?? 0,
                ];
            });
    }

    /**
     * Get overall usage statistics
     */
    private function getOverallUsageStatistics(string $period): array
    {
        $tenants = Tenant::active()->get();

        $totalUsers = $tenants->sum(function ($tenant) {
            return $tenant->run(fn() => \App\Models\User::count() ?? 0);
        });

        $totalVouchers = $tenants->sum(function ($tenant) {
            return $tenant->run(fn() => \App\Models\Voucher::count() ?? 0);
        });

        $activeTenants = $tenants->count();
        $avgUsersPerTenant = $activeTenants > 0 ? $totalUsers / $activeTenants : 0;
        $avgVouchersPerTenant = $activeTenants > 0 ? $totalVouchers / $activeTenants : 0;

        return [
            'total_users' => $totalUsers,
            'total_vouchers' => $totalVouchers,
            'active_tenants' => $activeTenants,
            'avg_users_per_tenant' => round($avgUsersPerTenant, 2),
            'avg_vouchers_per_tenant' => round($avgVouchersPerTenant, 2),
            'storage_used_mb' => $this->calculateTotalStorage(),
            'api_calls_today' => $this->getApiCallsToday(),
        ];
    }

    /**
     * Get usage trends
     */
    private function getUsageTrends(string $period, string $metric, Carbon $startDate, Carbon $endDate): array
    {
        $interval = $this->getIntervalForPeriod($period);

        $trends = collect();

        // This would typically query usage logs
        // For now, we'll generate sample trends

        $currentDate = $startDate->copy();
        while ($currentDate <= $endDate) {
            $dateKey = $currentDate->format($interval === '%Y-%m-%d' ? 'Y-m-d' : 'Y-m');

            $trends->push([
                'period' => $dateKey,
                'users' => rand(50, 200),
                'vouchers' => rand(100, 500),
                'api_calls' => rand(1000, 5000),
                'storage_mb' => rand(100, 1000),
            ]);

            $currentDate->add($interval === '%Y-%m-%d' ? '1 day' : '1 month');
        }

        return [
            'data' => $trends,
            'metric' => $metric,
            'peak_usage' => $trends->max($metric === 'all' ? 'users' : $metric),
            'growth_rate' => $this->calculateUsageGrowth($trends, $metric),
        ];
    }

    /**
     * Get top usage tenants
     */
    private function getTopUsageTenants(string $metric, Carbon $startDate, Carbon $endDate, int $limit)
    {
        $tenants = Tenant::active()->get();

        return $tenants->map(function ($tenant) use ($metric) {
            $usage = $tenant->getUsageStatistics();

            return [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'plan' => $tenant->plan,
                'users' => $usage['users']['total'] ?? 0,
                'vouchers_today' => $usage['vouchers_today']['total'] ?? 0,
                'storage_used' => $usage['storage']['used_mb'] ?? 0,
                'usage_percentage' => $this->calculateUsagePercentage($tenant, $usage),
            ];
        })
            ->sortByDesc(function ($tenant) use ($metric) {
                return $metric === 'all' ? $tenant['users'] : $tenant[$metric];
            })
            ->take($limit)
            ->values();
    }

    /**
     * Get limit utilization
     */
    private function getLimitUtilization(): array
    {
        $tenants = Tenant::active()->get();

        $utilizations = $tenants->map(function ($tenant) {
            $usage = $tenant->getUsageStatistics();

            return [
                'users' => [
                    'used' => $usage['users']['total'] ?? 0,
                    'limit' => $tenant->max_users,
                    'percentage' => $tenant->max_users > 0 ?
                        (($usage['users']['total'] ?? 0) / $tenant->max_users) * 100 : 0,
                ],
                'vouchers' => [
                    'used' => $usage['vouchers_today']['total'] ?? 0,
                    'limit' => $tenant->max_vouchers_per_day,
                    'percentage' => $tenant->max_vouchers_per_day > 0 ?
                        (($usage['vouchers_today']['total'] ?? 0) / $tenant->max_vouchers_per_day) * 100 : 0,
                ],
            ];
        });

        return [
            'avg_user_utilization' => $utilizations->avg('users.percentage'),
            'avg_voucher_utilization' => $utilizations->avg('vouchers.percentage'),
            'tenants_near_limit' => $utilizations->where('users.percentage', '>', 80)->count(),
            'tenants_over_limit' => $utilizations->where('users.percentage', '>', 100)->count(),
        ];
    }

    /**
     * Helper methods
     */
    private function getIntervalForPeriod(string $period): string
    {
        return match($period) {
            'day', 'week' => '%Y-%m-%d',
            'month', 'quarter', 'year' => '%Y-%m',
            default => '%Y-%m-%d',
        };
    }

    private function getChurnedTenantsCount(string $period): int
    {
        // This would typically query a churn log
        // For now, estimate based on suspended tenants created in period
        return Tenant::where('is_active', false)
            ->whereBetween('created_at', [
                now()->sub($period),
                now()
            ])
            ->count();
    }

    private function getAverageTenantsPerDay(string $period): float
    {
        $days = match($period) {
            'day' => 1,
            'week' => 7,
            'month' => 30,
            'quarter' => 90,
            'year' => 365,
            default => 30,
        };

        $newTenants = Tenant::where('created_at', '>=', now()->subDays($days))->count();

        return round($newTenants / $days, 2);
    }

    private function getRevenueForPeriod(Carbon $start, Carbon $end): float
    {
        // This would query actual payments
        // For now, estimate based on tenants created in period
        $tenants = Tenant::whereBetween('created_at', [$start, $end])->count();
        return $tenants * 50; // Average $50 per tenant
    }

    private function calculateChurnRate(): float
    {
        $startOfMonth = now()->startOfMonth();
        $startOfPreviousMonth = now()->subMonth()->startOfMonth();

        $tenantsStart = Tenant::where('created_at', '<', $startOfPreviousMonth)->count();
        $tenantsChurned = Tenant::where('is_active', false)
            ->whereBetween('updated_at', [$startOfPreviousMonth, $startOfMonth])
            ->count();

        return $tenantsStart > 0 ? ($tenantsChurned / $tenantsStart) * 100 : 0;
    }

    private function calculateLTV(): float
    {
        $avgRevenuePerTenant = 50; // Estimated average
        $avgLifetimeMonths = 12; // Estimated average lifetime

        return $avgRevenuePerTenant * $avgLifetimeMonths;
    }

    private function calculateTotalStorage(): int
    {
        // This would calculate actual storage usage
        // For now, estimate
        return Tenant::count() * 100; // 100MB per tenant average
    }

    private function getApiCallsToday(): int
    {
        // This would query API logs
        // For now, estimate
        return Tenant::count() * 50;
    }

    private function calculateUsageGrowth($trends, $metric): float
    {
        if ($trends->count() < 2) return 0;

        $first = $trends->first();
        $last = $trends->last();

        $firstValue = $metric === 'all' ? $first['users'] : $first[$metric];
        $lastValue = $metric === 'all' ? $last['users'] : $last[$metric];

        return $firstValue > 0 ? (($lastValue - $firstValue) / $firstValue) * 100 : 0;
    }

    private function calculateUsagePercentage(Tenant $tenant, array $usage): float
    {
        $totalLimit = $tenant->max_users + $tenant->max_vouchers_per_day;
        $totalUsed = ($usage['users']['total'] ?? 0) + ($usage['vouchers_today']['total'] ?? 0);

        return $totalLimit > 0 ? ($totalUsed / $totalLimit) * 100 : 0;
    }

    private function getUsageAnomalies(Carbon $startDate, Carbon $endDate): array
    {
        // Detect tenants with unusually high usage
        $tenants = Tenant::active()->get();

        return $tenants->filter(function ($tenant) {
            $usage = $tenant->getUsageStatistics();
            $userUtilization = ($usage['users']['total'] ?? 0) / $tenant->max_users * 100;
            $voucherUtilization = ($usage['vouchers_today']['total'] ?? 0) / $tenant->max_vouchers_per_day * 100;

            return $userUtilization > 90 || $voucherUtilization > 90;
        })
            ->map(function ($tenant) {
                $usage = $tenant->getUsageStatistics();

                return [
                    'tenant_id' => $tenant->id,
                    'tenant_name' => $tenant->name,
                    'issue' => 'High utilization',
                    'user_utilization' => round(($usage['users']['total'] ?? 0) / $tenant->max_users * 100, 2),
                    'voucher_utilization' => round(($usage['vouchers_today']['total'] ?? 0) / $tenant->max_vouchers_per_day * 100, 2),
                    'recommendation' => 'Consider upgrading plan',
                ];
            })
            ->values()
            ->toArray();
    }

    private function getUsageRecommendations(array $overallUsage, array $limitUtilization): array
    {
        $recommendations = [];

        if ($limitUtilization['avg_user_utilization'] > 70) {
            $recommendations[] = [
                'type' => 'warning',
                'message' => 'High average user utilization detected. Consider increasing default user limits.',
                'action' => 'Review plan limits',
            ];
        }

        if ($overallUsage['storage_used_mb'] > 10000) { // 10GB
            $recommendations[] = [
                'type' => 'warning',
                'message' => 'Storage usage is high. Consider implementing storage cleanup policies.',
                'action' => 'Review storage policies',
            ];
        }

        if ($overallUsage['api_calls_today'] > 10000) {
            $recommendations[] = [
                'type' => 'info',
                'message' => 'High API usage detected. Consider implementing rate limiting.',
                'action' => 'Review API usage',
            ];
        }

        return $recommendations;
    }

    private function getRevenueProjections(string $period): array
    {
        $currentRevenue = $this->getRevenueData($period, now()->startOfMonth(), now())['total_revenue'];
        $growthRate = 0.1; // 10% monthly growth

        $projections = [];
        for ($i = 1; $i <= 6; $i++) {
            $projectedRevenue = $currentRevenue * pow(1 + $growthRate, $i);
            $projections[] = [
                'month' => now()->addMonths($i)->format('M Y'),
                'projected_revenue' => round($projectedRevenue, 2),
                'growth_from_current' => round((($projectedRevenue - $currentRevenue) / $currentRevenue) * 100, 2),
            ];
        }

        return $projections;
    }

    /**
     * Export data methods (for CSV/Excel export)
     */
    private function getTenantsExportData($query): array
    {
        return $query->get()->map(function ($tenant) {
            return [
                'ID' => $tenant->id,
                'Name' => $tenant->name,
                'Email' => $tenant->email,
                'Plan' => $tenant->plan,
                'Status' => $tenant->is_active ? 'Active' : 'Suspended',
                'Users Limit' => $tenant->max_users,
                'Vouchers Limit' => $tenant->max_vouchers_per_day,
                'Billing Cycle' => $tenant->billing_cycle,
                'Created Date' => $tenant->created_at->format('Y-m-d'),
                'Next Billing' => $tenant->next_billing_date?->format('Y-m-d'),
            ];
        })->toArray();
    }

    private function getRevenueExportData(array $revenueData, array $recurringRevenue, $topTenants): array
    {
        return [
            'summary' => [
                ['Metric', 'Value'],
                ['Total Revenue', $revenueData['total_revenue']],
                ['Monthly Recurring Revenue', $recurringRevenue['mrr']],
                ['Annual Recurring Revenue', $recurringRevenue['arr']],
                ['Churn Rate', $recurringRevenue['churn_rate'] . '%'],
                ['Growth Rate', $revenueData['growth_rate'] . '%'],
            ],
            'top_tenants' => $topTenants->map(function ($tenant) {
                return [
                    'Tenant Name' => $tenant['name'],
                    'Plan' => $tenant['plan'],
                    'Revenue' => $tenant['revenue'],
                    'Users' => $tenant['user_count'],
                    'Status' => $tenant['status'],
                ];
            })->toArray(),
            'daily_revenue' => collect($revenueData['daily_data'])->map(function ($revenue, $date) {
                return ['Date' => $date, 'Revenue' => $revenue];
            })->values()->toArray(),
        ];
    }

    private function getUsageExportData(array $overallUsage, array $usageTrends, $topUsageTenants): array
    {
        return [
            'summary' => [
                ['Metric', 'Value'],
                ['Total Users', $overallUsage['total_users']],
                ['Total Vouchers', $overallUsage['total_vouchers']],
                ['Active Tenants', $overallUsage['active_tenants']],
                ['Avg Users per Tenant', $overallUsage['avg_users_per_tenant']],
                ['Storage Used (MB)', $overallUsage['storage_used_mb']],
                ['API Calls Today', $overallUsage['api_calls_today']],
            ],
            'top_usage_tenants' => $topUsageTenants->map(function ($tenant) {
                return [
                    'Tenant Name' => $tenant['name'],
                    'Plan' => $tenant['plan'],
                    'Users' => $tenant['users'],
                    'Vouchers Today' => $tenant['vouchers_today'],
                    'Storage Used' => $tenant['storage_used'],
                    'Usage %' => $tenant['usage_percentage'] . '%',
                ];
            })->toArray(),
            'usage_trends' => $usageTrends['data']->map(function ($trend) {
                return [
                    'Period' => $trend['period'],
                    'Users' => $trend['users'],
                    'Vouchers' => $trend['vouchers'],
                    'API Calls' => $trend['api_calls'],
                    'Storage (MB)' => $trend['storage_mb'],
                ];
            })->toArray(),
        ];
    }
}
