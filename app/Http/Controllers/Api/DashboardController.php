<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\Voucher;
use App\Models\MikroTikDevice;
use App\Models\SmsLog;
use App\Models\PaymentGateway;
use App\Models\Tenant;
use App\Services\DatabaseContextService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Carbon\Carbon;

class DashboardController extends Controller
{
    private ?Tenant $currentTenant;
    private DatabaseContextService $dbContext;

    public function __construct()
    {
        $this->currentTenant = $this->resolveTenant();
        $this->dbContext = new DatabaseContextService();
    }

    /**
     * Resolve the current tenant from the request
     */
    private function resolveTenant(): ?Tenant
    {
        try {
            // Method 1: From authenticated user (most reliable for API calls)
            if (auth()->check() && auth()->user()->tenant_id) {
                return Tenant::on('pgsql_central')->find(auth()->user()->tenant_id);
            }

            // Method 2: From header (for API calls)
            if (request()->hasHeader('X-Tenant-ID')) {
                return Tenant::on('pgsql_central')->where('id', request()->header('X-Tenant-ID'))->first();
            }

            // Method 3: From subdomain (for web calls)
            $host = request()->getHost();
            if (str_contains($host, '.')) {
                $subdomain = explode('.', $host)[0];
                if ($subdomain && $subdomain !== 'www' && $subdomain !== 'api') {
                    return Tenant::on('pgsql_central')->where('slug', $subdomain)->first();
                }
            }

            return null; // No tenant resolved
        } catch (\Exception $e) {
            Log::warning('Failed to resolve tenant in DashboardController', [
                'error' => $e->getMessage(),
                'host' => request()->getHost(),
                'headers' => request()->headers->all(),
                'user_id' => auth()->id(),
                'user_tenant_id' => auth()->check() ? auth()->user()->tenant_id : null
            ]);
            return null;
        }
    }

    /**
     * Get dashboard statistics
     */
    public function stats(Request $request): JsonResponse
    {
        try {
            $user = auth()->user();
            $isGlobalAdmin = DatabaseContextService::isGlobalAdmin($user);
            
            $cacheKey = $isGlobalAdmin 
                ? 'dashboard_stats_global_admin_' . date('Y-m-d-H')
                : 'dashboard_stats_tenant_' . ($user->tenant_id ?? 'unknown') . '_' . date('Y-m-d-H');
            
            $stats = Cache::remember($cacheKey, 300, function () use ($user, $isGlobalAdmin) {
                if ($isGlobalAdmin) {
                    return $this->getGlobalAdminStats();
                } else {
                    return $this->getTenantStats($user->tenant_id);
                }
            });

            return response()->json([
                'success' => true,
                'data' => $stats,
                'context' => [
                    'is_global_admin' => $isGlobalAdmin,
                    'tenant_id' => $user->tenant_id ?? null,
                    'user_role' => $user->role ?? null,
                ],
                'timestamp' => now()->toISOString(),
            ]);
        } catch (\Exception $e) {
            Log::error('Dashboard stats error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to load dashboard statistics',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Get recent payments for dashboard
     */
    public function recentPayments(Request $request): JsonResponse
    {
        try {
            $limit = $request->get('limit', 10);
            $user = auth()->user();
            
            if (DatabaseContextService::isGlobalAdmin($user)) {
                // Global admin sees payments from all tenants
                $payments = $this->getGlobalRecentPayments($limit);
            } else {
                // Tenant user sees only their payments
                $payments = $this->getTenantRecentPayments($user->tenant_id, $limit);
            }

            return response()->json([
                'success' => true,
                'data' => $payments,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to load recent payments',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Get recent vouchers for dashboard
     */
    public function recentVouchers(Request $request): JsonResponse
    {
        try {
            $limit = $request->get('limit', 10);
            $user = auth()->user();
            
            if (DatabaseContextService::isGlobalAdmin($user)) {
                // Global admin sees vouchers from all tenants
                $vouchers = $this->getGlobalRecentVouchers($limit);
            } else {
                // Tenant user sees only their vouchers
                $vouchers = $this->getTenantRecentVouchers($user->tenant_id, $limit);
            }

            return response()->json([
                'success' => true,
                'data' => $vouchers,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to load recent vouchers',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Get global admin statistics (aggregated from all tenants)
     */
    private function getGlobalAdminStats(): array
    {
        $stats = [
            'overview' => [
                'total_tenants' => 0,
                'active_tenants' => 0,
                'total_customers' => 0,
                'total_payments' => 0,
                'total_vouchers' => 0,
                'total_revenue' => 0,
            ],
            'by_plan' => [
                'starter' => ['count' => 0, 'revenue' => 0],
                'professional' => ['count' => 0, 'revenue' => 0],
                'enterprise' => ['count' => 0, 'revenue' => 0],
            ],
            'tenants' => [],
            'recent_activity' => [],
        ];

        // Get tenant information from central database
        $tenants = DB::connection('pgsql_central')
            ->table('tenants')
            ->orderBy('created_at', 'desc')
            ->get();

        $stats['overview']['total_tenants'] = $tenants->count();
        $stats['overview']['active_tenants'] = $tenants->where('is_active', true)->count();

        foreach ($tenants as $tenant) {
            $stats['by_plan'][$tenant->plan]['count']++;
            
            try {
                // Get tenant-specific stats from tenant database
                $tenantStats = $this->getTenantStatsFromDatabase($tenant->id);
                
                $stats['tenants'][] = [
                    'id' => $tenant->id,
                    'name' => $tenant->name,
                    'slug' => $tenant->slug,
                    'plan' => $tenant->plan,
                    'is_active' => $tenant->is_active,
                    'created_at' => $tenant->created_at,
                    'stats' => $tenantStats
                ];
                
                // Aggregate totals
                $stats['overview']['total_customers'] += $tenantStats['customers']['total'] ?? 0;
                $stats['overview']['total_payments'] += $tenantStats['payments']['total'] ?? 0;
                $stats['overview']['total_vouchers'] += $tenantStats['vouchers']['total'] ?? 0;
                $stats['overview']['total_revenue'] += $tenantStats['revenue']['monthly'] ?? 0;
                $stats['by_plan'][$tenant->plan]['revenue'] += $tenantStats['revenue']['monthly'] ?? 0;
                
            } catch (\Exception $e) {
                Log::warning("Failed to get stats for tenant {$tenant->id}", [
                    'tenant' => $tenant->id,
                    'error' => $e->getMessage()
                ]);
            }
        }

        // Get recent activity across all tenants
        $stats['recent_activity'] = $this->getGlobalRecentActivity();

        return $stats;
    }

    /**
     * Get tenant-specific statistics
     */
    private function getTenantStats(?string $tenantId): array
    {
        if (!$tenantId) {
            throw new \Exception('Tenant ID required');
        }

        return $this->getTenantStatsFromDatabase($tenantId);
    }

    /**
     * Get tenant statistics from database
     */
    private function getTenantStatsFromDatabase(string $tenantId): array
    {
        $today = Carbon::today();
        $thisMonth = Carbon::now()->startOfMonth();
        $lastMonth = Carbon::now()->subMonth()->startOfMonth();
        $lastMonthEnd = Carbon::now()->subMonth()->endOfMonth();

        // Customer metrics
        $customerQuery = DB::connection('pgsql')
            ->table('customers')
            ->where('tenant_id', $tenantId);

        $customerStats = [
            'total' => (clone $customerQuery)->count(),
            'active' => (clone $customerQuery)->where('is_active', true)->count(),
            'new_today' => (clone $customerQuery)->whereDate('created_at', $today)->count(),
            'new_this_month' => (clone $customerQuery)->where('created_at', '>=', $thisMonth)->count(),
        ];

        $customerStats['active_percentage'] = $customerStats['total'] > 0 
            ? round(($customerStats['active'] / $customerStats['total']) * 100, 2) 
            : 0;

        // Payment metrics
        $paymentQuery = DB::connection('pgsql')
            ->table('payments')
            ->where('tenant_id', $tenantId);

        $paymentStats = [
            'total' => (clone $paymentQuery)->count(),
            'completed' => (clone $paymentQuery)->where('status', 'completed')->count(),
            'pending' => (clone $paymentQuery)->where('status', 'pending')->count(),
            'failed' => (clone $paymentQuery)->where('status', 'failed')->count(),
            'today' => (clone $paymentQuery)->whereDate('created_at', $today)->count(),
        ];

        $paymentStats['success_rate'] = $paymentStats['total'] > 0 
            ? round(($paymentStats['completed'] / $paymentStats['total']) * 100, 2) 
            : 0;

        // Revenue metrics
        $todayRevenue = DB::connection('pgsql')
            ->table('payments')
            ->where('tenant_id', $tenantId)
            ->where('status', 'completed')
            ->whereDate('paid_at', $today)
            ->sum('amount');

        $monthlyRevenue = DB::connection('pgsql')
            ->table('payments')
            ->where('tenant_id', $tenantId)
            ->where('status', 'completed')
            ->where('paid_at', '>=', $thisMonth)
            ->sum('amount');

        $lastMonthRevenue = DB::connection('pgsql')
            ->table('payments')
            ->where('tenant_id', $tenantId)
            ->where('status', 'completed')
            ->whereBetween('paid_at', [$lastMonth, $lastMonthEnd])
            ->sum('amount');

        $revenueGrowth = $lastMonthRevenue > 0 
            ? (($monthlyRevenue - $lastMonthRevenue) / $lastMonthRevenue) * 100 
            : 0;

        // Voucher metrics
        $voucherQuery = DB::connection('pgsql')
            ->table('vouchers')
            ->where('tenant_id', $tenantId);

        $voucherStats = [
            'total' => (clone $voucherQuery)->count(),
            'active' => (clone $voucherQuery)->where('status', 'active')->count(),
            'expired' => (clone $voucherQuery)->where('status', 'expired')->count(),
            'used' => (clone $voucherQuery)->where('status', 'used')->count(),
            'generated_today' => (clone $voucherQuery)->whereDate('created_at', $today)->count(),
            'generated_this_month' => (clone $voucherQuery)->where('created_at', '>=', $thisMonth)->count(),
        ];

        $voucherStats['utilization_rate'] = $voucherStats['total'] > 0 
            ? round(($voucherStats['active'] / $voucherStats['total']) * 100, 2) 
            : 0;

        // MikroTik device metrics (if table exists)
        $deviceStats = [
            'total_devices' => 0,
            'online' => 0,
            'offline' => 0,
            'uptime_percentage' => 0,
        ];

        try {
            if (DB::connection('pgsql')->getSchemaBuilder()->hasTable('mikrotik_devices')) {
                // MikroTik devices are global resources, not tenant-specific
                $deviceStats = [
                    'total_devices' => DB::connection('pgsql')->table('mikrotik_devices')->count(),
                    'online' => DB::connection('pgsql')->table('mikrotik_devices')->where('status', 'online')->count(),
                    'offline' => DB::connection('pgsql')->table('mikrotik_devices')->where('status', 'offline')->count(),
                ];

                $deviceStats['uptime_percentage'] = $deviceStats['total_devices'] > 0 
                    ? round(($deviceStats['online'] / $deviceStats['total_devices']) * 100, 2) 
                    : 0;
            }
        } catch (\Exception $e) {
            Log::warning('Failed to get MikroTik device stats', ['error' => $e->getMessage()]);
        }

        return [
            'customers' => $customerStats,
            'payments' => $paymentStats,
            'vouchers' => $voucherStats,
            'mikrotik' => $deviceStats,
            'revenue' => [
                'today' => (float) $todayRevenue,
                'monthly' => (float) $monthlyRevenue,
                'growth_percentage' => round($revenueGrowth, 2),
                'analytics' => $this->getRevenueAnalytics($tenantId),
            ],
            'system_health' => $this->getSystemHealth($tenantId),
            'recent_activity' => $this->getRecentActivity($tenantId),
        ];
    }

    /**
     * Get global recent payments (from all tenants)
     */
    private function getGlobalRecentPayments(int $limit): array
    {
        $payments = [];
        
        // Get all active tenants
        $tenants = DB::connection('pgsql_central')
            ->table('tenants')
            ->where('is_active', true)
            ->get();

        foreach ($tenants as $tenant) {
            try {
                $tenantPayments = DB::connection('pgsql')
                    ->table('payments')
                    ->join('customers', 'payments.customer_id', '=', 'customers.id')
                    ->leftJoin('vouchers', 'payments.id', '=', 'vouchers.payment_id')
                    ->select([
                        'payments.id',
                        'payments.amount',
                        'payments.currency',
                        'payments.status',
                        'payments.created_at',
                        'payments.paid_at',
                        'customers.name as customer_name',
                        'customers.phone as customer_phone',
                        'vouchers.code as voucher_code',
                        'vouchers.validity_hours as duration_hours'
                    ])
                    ->where('payments.tenant_id', $tenant->id)
                    ->orderBy('payments.created_at', 'desc')
                    ->limit($limit)
                    ->get();

                foreach ($tenantPayments as $payment) {
                    $payments[] = [
                        'id' => $payment->id,
                        'amount' => $payment->amount,
                        'currency' => $payment->currency,
                        'status' => $payment->status,
                        'created_at' => $payment->created_at,
                        'processed_at' => $payment->paid_at,
                        'customer' => [
                            'name' => $payment->customer_name,
                            'phone' => $payment->customer_phone,
                        ],
                        'voucher' => $payment->voucher_code ? [
                            'code' => $payment->voucher_code,
                            'duration_hours' => $payment->duration_hours,
                        ] : null,
                        'tenant' => [
                            'id' => $tenant->id,
                            'name' => $tenant->name,
                            'slug' => $tenant->slug,
                        ]
                    ];
                }
            } catch (\Exception $e) {
                Log::warning("Failed to get payments for tenant {$tenant->id}", [
                    'error' => $e->getMessage()
                ]);
            }
        }

        // Sort by created_at and limit
        usort($payments, function ($a, $b) {
            return strtotime($b['created_at']) - strtotime($a['created_at']);
        });

        return array_slice($payments, 0, $limit);
    }

    /**
     * Get tenant recent payments
     */
    private function getTenantRecentPayments(string $tenantId, int $limit): array
    {
        $payments = DB::connection('pgsql')
            ->table('payments')
            ->join('customers', 'payments.customer_id', '=', 'customers.id')
            ->leftJoin('vouchers', 'payments.id', '=', 'vouchers.payment_id')
            ->select([
                'payments.id',
                'payments.amount',
                'payments.currency',
                'payments.status',
                'payments.created_at',
                'payments.paid_at',
                'customers.id as customer_id',
                'customers.name as customer_name',
                'customers.phone as customer_phone',
                'vouchers.id as voucher_id',
                'vouchers.code as voucher_code',
                'vouchers.validity_hours as duration_hours'
            ])
            ->where('payments.tenant_id', $tenantId)
            ->orderBy('payments.created_at', 'desc')
            ->limit($limit)
            ->get();

        return $payments->map(function ($payment) {
            return [
                'id' => $payment->id,
                'amount' => $payment->amount,
                'currency' => $payment->currency,
                'status' => $payment->status,
                'created_at' => $payment->created_at,
                'processed_at' => $payment->paid_at,
                'customer' => [
                    'id' => $payment->customer_id,
                    'name' => $payment->customer_name,
                    'phone' => $payment->customer_phone,
                ],
                'voucher' => $payment->voucher_code ? [
                    'id' => $payment->voucher_id,
                    'code' => $payment->voucher_code,
                    'duration_hours' => $payment->duration_hours,
                ] : null,
            ];
        })->toArray();
    }

    /**
     * Get global recent vouchers (from all tenants)
     */
    private function getGlobalRecentVouchers(int $limit): array
    {
        $vouchers = [];
        
        // Get all active tenants
        $tenants = DB::connection('pgsql_central')
            ->table('tenants')
            ->where('is_active', true)
            ->get();

        foreach ($tenants as $tenant) {
            try {
                $tenantVouchers = DB::connection('pgsql')
                    ->table('vouchers')
                    ->leftJoin('customers', 'vouchers.customer_id', '=', 'customers.id')
                    ->select([
                        'vouchers.id',
                        'vouchers.code',
                        'vouchers.price as amount',
                        'vouchers.validity_hours as duration_hours',
                        'vouchers.status',
                        'vouchers.created_at',
                        'vouchers.activated_at',
                        'vouchers.expires_at',
                        'customers.name as customer_name',
                        'customers.phone as customer_phone'
                    ])
                    ->where('vouchers.tenant_id', $tenant->id)
                    ->orderBy('vouchers.created_at', 'desc')
                    ->limit($limit)
                    ->get();

                foreach ($tenantVouchers as $voucher) {
                    $vouchers[] = [
                        'id' => $voucher->id,
                        'code' => $voucher->code,
                        'amount' => $voucher->amount,
                        'duration_hours' => $voucher->duration_hours,
                        'status' => $voucher->status,
                        'created_at' => $voucher->created_at,
                        'activated_at' => $voucher->activated_at,
                        'expires_at' => $voucher->expires_at,
                        'customer' => $voucher->customer_name ? [
                            'name' => $voucher->customer_name,
                            'phone' => $voucher->customer_phone,
                        ] : null,
                        'tenant' => [
                            'id' => $tenant->id,
                            'name' => $tenant->name,
                            'slug' => $tenant->slug,
                        ]
                    ];
                }
            } catch (\Exception $e) {
                Log::warning("Failed to get vouchers for tenant {$tenant->id}", [
                    'error' => $e->getMessage()
                ]);
            }
        }

        // Sort by created_at and limit
        usort($vouchers, function ($a, $b) {
            return strtotime($b['created_at']) - strtotime($a['created_at']);
        });

        return array_slice($vouchers, 0, $limit);
    }

    /**
     * Get tenant recent vouchers
     */
    private function getTenantRecentVouchers(string $tenantId, int $limit): array
    {
        $vouchers = DB::connection('pgsql')
            ->table('vouchers')
            ->leftJoin('customers', 'vouchers.customer_id', '=', 'customers.id')
            ->select([
                'vouchers.id',
                'vouchers.code',
                'vouchers.price as amount',
                'vouchers.validity_hours as duration_hours',
                'vouchers.status',
                'vouchers.created_at',
                'vouchers.activated_at',
                'vouchers.expires_at',
                'customers.id as customer_id',
                'customers.name as customer_name',
                'customers.phone as customer_phone'
            ])
            ->where('vouchers.tenant_id', $tenantId)
            ->orderBy('vouchers.created_at', 'desc')
            ->limit($limit)
            ->get();

        return $vouchers->map(function ($voucher) {
            return [
                'id' => $voucher->id,
                'code' => $voucher->code,
                'amount' => $voucher->amount,
                'duration_hours' => $voucher->duration_hours,
                'status' => $voucher->status,
                'created_at' => $voucher->created_at,
                'activated_at' => $voucher->activated_at,
                'expires_at' => $voucher->expires_at,
                'customer' => $voucher->customer_name ? [
                    'id' => $voucher->customer_id,
                    'name' => $voucher->customer_name,
                    'phone' => $voucher->customer_phone,
                ] : null,
            ];
        })->toArray();
    }

    /**
     * Get SMS balance from configuration or API
     */
    private function getSmsBalance(): float
    {
        // This would typically call the SMS provider API
        // For now, return a cached value or default
        return Cache::get('sms_balance_' . (auth()->user()->tenant_id ?? 'global'), 1000.0);
    }

    /**
     * Get system health status
     */
    private function getSystemHealth(?string $tenantId = null): array
    {
        $health = [
            'overall_status' => 'healthy',
            'checks' => [],
        ];

        // Database check
        try {
            DB::connection('pgsql')->getPdo();
            $health['checks']['database'] = [
                'status' => 'healthy',
                'message' => 'Database connection successful',
                'last_check' => now()->toISOString(),
            ];
        } catch (\Exception $e) {
            $health['checks']['database'] = [
                'status' => 'unhealthy',
                'message' => 'Database connection failed',
                'last_check' => now()->toISOString(),
            ];
            $health['overall_status'] = 'unhealthy';
        }

        // MikroTik devices check
        try {
            if (DB::connection('pgsql')->getSchemaBuilder()->hasTable('mikrotik_devices')) {
                $query = DB::connection('pgsql')->table('mikrotik_devices');
                
                if ($tenantId) {
                    $query->where('tenant_id', $tenantId);
                }
                
                $offlineDevices = (clone $query)->where('status', 'offline')->count();
                $totalDevices = $query->count();
                
                if ($totalDevices > 0) {
                    $onlinePercentage = (($totalDevices - $offlineDevices) / $totalDevices) * 100;
                    $health['checks']['mikrotik'] = [
                        'status' => $onlinePercentage >= 80 ? 'healthy' : ($onlinePercentage >= 50 ? 'warning' : 'unhealthy'),
                        'message' => "{$onlinePercentage}% of devices online",
                        'online_devices' => $totalDevices - $offlineDevices,
                        'total_devices' => $totalDevices,
                        'last_check' => now()->toISOString(),
                    ];
                }
            }
        } catch (\Exception $e) {
            Log::warning('MikroTik health check failed', ['error' => $e->getMessage()]);
        }

        // Payment gateways check
        try {
            if (DB::connection('pgsql')->getSchemaBuilder()->hasTable('payment_gateways')) {
                // Payment gateways are global resources, not tenant-specific
                $activeGateways = DB::connection('pgsql')->table('payment_gateways')->where('is_active', true)->count();
                
                $health['checks']['payment_gateways'] = [
                    'status' => $activeGateways > 0 ? 'healthy' : 'warning',
                    'message' => "{$activeGateways} active payment gateway(s)",
                    'active_gateways' => $activeGateways,
                    'last_check' => now()->toISOString(),
                ];
            }
        } catch (\Exception $e) {
            Log::warning('Payment gateway health check failed', ['error' => $e->getMessage()]);
        }

        // SMS service check
        $smsBalance = $this->getSmsBalance();
        $health['checks']['sms_service'] = [
            'status' => $smsBalance > 100 ? 'healthy' : ($smsBalance > 10 ? 'warning' : 'critical'),
            'message' => "SMS balance: {$smsBalance}",
            'balance' => $smsBalance,
            'last_check' => now()->toISOString(),
        ];

        return $health;
    }

    /**
     * Get recent activity for dashboard
     */
    private function getRecentActivity(?string $tenantId = null): array
    {
        $activities = [];

        // Recent payments
        $paymentQuery = DB::connection('pgsql')
            ->table('payments')
            ->join('customers', 'payments.customer_id', '=', 'customers.id')
            ->select([
                'payments.id',
                'payments.amount',
                'payments.currency',
                'payments.status',
                'payments.created_at',
                'customers.name as customer_name'
            ])
            ->where('payments.created_at', '>=', now()->subHours(24))
            ->orderBy('payments.created_at', 'desc')
            ->limit(5);

        if ($tenantId) {
            $paymentQuery->where('payments.tenant_id', $tenantId);
        }

        $recentPayments = $paymentQuery->get();

        foreach ($recentPayments as $payment) {
            $activities[] = [
                'type' => 'payment',
                'title' => 'Payment Received',
                'description' => "Payment of {$payment->currency} {$payment->amount} from {$payment->customer_name}",
                'timestamp' => $payment->created_at,
                'status' => $payment->status,
                'icon' => 'credit-card',
                'color' => $payment->status === 'completed' ? 'green' : 'yellow',
            ];
        }

        // Recent vouchers
        $voucherQuery = DB::connection('pgsql')
            ->table('vouchers')
            ->leftJoin('customers', 'vouchers.customer_id', '=', 'customers.id')
            ->select([
                'vouchers.id',
                'vouchers.code',
                'vouchers.status',
                'vouchers.created_at',
                'customers.name as customer_name'
            ])
            ->where('vouchers.created_at', '>=', now()->subHours(24))
            ->orderBy('vouchers.created_at', 'desc')
            ->limit(5);

        if ($tenantId) {
            $voucherQuery->where('vouchers.tenant_id', $tenantId);
        }

        $recentVouchers = $voucherQuery->get();

        foreach ($recentVouchers as $voucher) {
            $activities[] = [
                'type' => 'voucher',
                'title' => 'Voucher Generated',
                'description' => "Voucher {$voucher->code} created" . ($voucher->customer_name ? " for {$voucher->customer_name}" : ""),
                'timestamp' => $voucher->created_at,
                'status' => $voucher->status,
                'icon' => 'ticket',
                'color' => 'blue',
            ];
        }

        // Recent customers
        $customerQuery = DB::connection('pgsql')
            ->table('customers')
            ->select(['id', 'name', 'created_at'])
            ->where('created_at', '>=', now()->subHours(24))
            ->orderBy('created_at', 'desc')
            ->limit(3);

        if ($tenantId) {
            $customerQuery->where('tenant_id', $tenantId);
        }

        $recentCustomers = $customerQuery->get();

        foreach ($recentCustomers as $customer) {
            $activities[] = [
                'type' => 'customer',
                'title' => 'New Customer',
                'description' => "Customer {$customer->name} registered",
                'timestamp' => $customer->created_at,
                'status' => 'active',
                'icon' => 'user-plus',
                'color' => 'green',
            ];
        }

        // Sort by timestamp and limit
        usort($activities, function ($a, $b) {
            return strtotime($b['timestamp']) - strtotime($a['timestamp']);
        });

        return array_slice($activities, 0, 10);
    }

    /**
     * Get global recent activity (from all tenants)
     */
    private function getGlobalRecentActivity(): array
    {
        return $this->getRecentActivity(); // No tenant filter for global admin
    }

    /**
     * Get revenue analytics for charts
     */
    private function getRevenueAnalytics(?string $tenantId = null): array
    {
        $days = [];
        $revenues = [];

        // Get last 30 days of revenue data
        for ($i = 29; $i >= 0; $i--) {
            $date = Carbon::now()->subDays($i);
            
            $query = DB::connection('pgsql')
                ->table('payments')
                ->where('status', 'completed')
                ->whereDate('paid_at', $date);

            if ($tenantId) {
                $query->where('tenant_id', $tenantId);
            }

            $dayRevenue = $query->sum('amount');

            $days[] = $date->format('M d');
            $revenues[] = (float) $dayRevenue;
        }

        return [
            'labels' => $days,
            'datasets' => [
                [
                    'label' => 'Daily Revenue',
                    'data' => $revenues,
                    'borderColor' => '#3B82F6',
                    'backgroundColor' => 'rgba(59, 130, 246, 0.1)',
                    'tension' => 0.4,
                ]
            ]
        ];
    }
}