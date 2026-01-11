<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\Voucher;
use App\Models\MikroTikDevice;
use App\Models\SmsLog;
use App\Models\PaymentGateway;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DashboardController extends Controller
{
    /**
     * Get dashboard statistics
     */
    public function stats(Request $request): JsonResponse
    {
        try {
            $cacheKey = 'dashboard_stats_' . tenant('id') . '_' . date('Y-m-d-H');
            
            $stats = Cache::remember($cacheKey, 300, function () {
                return $this->calculateDashboardStats();
            });

            return response()->json([
                'success' => true,
                'data' => $stats,
                'timestamp' => now()->toISOString(),
            ]);
        } catch (\Exception $e) {
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
            
            $payments = Payment::with(['customer', 'voucher'])
                ->orderBy('created_at', 'desc')
                ->limit($limit)
                ->get()
                ->map(function ($payment) {
                    return [
                        'id' => $payment->id,
                        'amount' => $payment->amount,
                        'currency' => $payment->currency,
                        'status' => $payment->status,
                        'created_at' => $payment->created_at->toISOString(),
                        'processed_at' => $payment->processed_at?->toISOString(),
                        'customer' => [
                            'id' => $payment->customer->id,
                            'name' => $payment->customer->name,
                            'phone' => $payment->customer->phone,
                        ],
                        'voucher' => $payment->voucher ? [
                            'id' => $payment->voucher->id,
                            'code' => $payment->voucher->code,
                            'duration_hours' => $payment->voucher->duration_hours,
                        ] : null,
                    ];
                });

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
            
            $vouchers = Voucher::with(['customer'])
                ->orderBy('created_at', 'desc')
                ->limit($limit)
                ->get()
                ->map(function ($voucher) {
                    return [
                        'id' => $voucher->id,
                        'code' => $voucher->code,
                        'amount' => $voucher->amount,
                        'duration_hours' => $voucher->duration_hours,
                        'status' => $voucher->status,
                        'created_at' => $voucher->created_at->toISOString(),
                        'activated_at' => $voucher->activated_at?->toISOString(),
                        'expires_at' => $voucher->expires_at?->toISOString(),
                        'customer' => $voucher->customer ? [
                            'id' => $voucher->customer->id,
                            'name' => $voucher->customer->name,
                            'phone' => $voucher->customer->phone,
                        ] : null,
                    ];
                });

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
     * Calculate comprehensive dashboard statistics
     */
    private function calculateDashboardStats(): array
    {
        $today = Carbon::today();
        $thisMonth = Carbon::now()->startOfMonth();
        $lastMonth = Carbon::now()->subMonth()->startOfMonth();
        $lastMonthEnd = Carbon::now()->subMonth()->endOfMonth();

        // Revenue metrics
        $todayRevenue = Payment::where('status', 'completed')
            ->whereDate('processed_at', $today)
            ->sum('amount');

        $monthlyRevenue = Payment::where('status', 'completed')
            ->where('processed_at', '>=', $thisMonth)
            ->sum('amount');

        $lastMonthRevenue = Payment::where('status', 'completed')
            ->whereBetween('processed_at', [$lastMonth, $lastMonthEnd])
            ->sum('amount');

        $revenueGrowth = $lastMonthRevenue > 0 
            ? (($monthlyRevenue - $lastMonthRevenue) / $lastMonthRevenue) * 100 
            : 0;

        // Customer metrics
        $totalCustomers = Customer::count();
        $activeCustomers = Customer::where('status', 'active')->count();
        $newCustomersToday = Customer::whereDate('created_at', $today)->count();
        $newCustomersThisMonth = Customer::where('created_at', '>=', $thisMonth)->count();

        // Voucher metrics
        $totalVouchers = Voucher::count();
        $activeVouchers = Voucher::where('status', 'active')->count();
        $expiredVouchers = Voucher::where('status', 'expired')->count();
        $vouchersGeneratedToday = Voucher::whereDate('created_at', $today)->count();
        $vouchersGeneratedThisMonth = Voucher::where('created_at', '>=', $thisMonth)->count();

        // Payment metrics
        $totalPayments = Payment::count();
        $completedPayments = Payment::where('status', 'completed')->count();
        $pendingPayments = Payment::where('status', 'pending')->count();
        $failedPayments = Payment::where('status', 'failed')->count();
        $paymentsToday = Payment::whereDate('created_at', $today)->count();

        // MikroTik device metrics
        $totalDevices = MikroTikDevice::count();
        $onlineDevices = MikroTikDevice::where('status', 'online')->count();
        $offlineDevices = MikroTikDevice::where('status', 'offline')->count();

        // SMS metrics
        $smsBalance = $this->getSmsBalance();
        $smsSentToday = SmsLog::whereDate('created_at', $today)->count();
        $smsDeliveredToday = SmsLog::whereDate('created_at', $today)
            ->where('status', 'delivered')->count();

        // System health
        $systemHealth = $this->getSystemHealth();

        // Recent activity
        $recentActivity = $this->getRecentActivity();

        // Revenue analytics for charts
        $revenueAnalytics = $this->getRevenueAnalytics();

        return [
            'revenue' => [
                'today' => $todayRevenue,
                'monthly' => $monthlyRevenue,
                'growth_percentage' => round($revenueGrowth, 2),
                'analytics' => $revenueAnalytics,
            ],
            'customers' => [
                'total' => $totalCustomers,
                'active' => $activeCustomers,
                'new_today' => $newCustomersToday,
                'new_this_month' => $newCustomersThisMonth,
                'active_percentage' => $totalCustomers > 0 ? round(($activeCustomers / $totalCustomers) * 100, 2) : 0,
            ],
            'vouchers' => [
                'total' => $totalVouchers,
                'active' => $activeVouchers,
                'expired' => $expiredVouchers,
                'generated_today' => $vouchersGeneratedToday,
                'generated_this_month' => $vouchersGeneratedThisMonth,
                'utilization_rate' => $totalVouchers > 0 ? round(($activeVouchers / $totalVouchers) * 100, 2) : 0,
            ],
            'payments' => [
                'total' => $totalPayments,
                'completed' => $completedPayments,
                'pending' => $pendingPayments,
                'failed' => $failedPayments,
                'today' => $paymentsToday,
                'success_rate' => $totalPayments > 0 ? round(($completedPayments / $totalPayments) * 100, 2) : 0,
            ],
            'mikrotik' => [
                'total_devices' => $totalDevices,
                'online' => $onlineDevices,
                'offline' => $offlineDevices,
                'uptime_percentage' => $totalDevices > 0 ? round(($onlineDevices / $totalDevices) * 100, 2) : 0,
            ],
            'sms' => [
                'balance' => $smsBalance,
                'sent_today' => $smsSentToday,
                'delivered_today' => $smsDeliveredToday,
                'delivery_rate' => $smsSentToday > 0 ? round(($smsDeliveredToday / $smsSentToday) * 100, 2) : 0,
            ],
            'system_health' => $systemHealth,
            'recent_activity' => $recentActivity,
        ];
    }

    /**
     * Get SMS balance from configuration or API
     */
    private function getSmsBalance(): float
    {
        // This would typically call the SMS provider API
        // For now, return a cached value or default
        return Cache::get('sms_balance_' . tenant('id'), 1000.0);
    }

    /**
     * Get system health status
     */
    private function getSystemHealth(): array
    {
        $health = [
            'overall_status' => 'healthy',
            'checks' => [],
        ];

        // Database check
        try {
            DB::connection()->getPdo();
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
        $offlineDevices = MikroTikDevice::where('status', 'offline')->count();
        $totalDevices = MikroTikDevice::count();
        
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

        // Payment gateways check
        $activeGateways = PaymentGateway::where('is_active', true)->count();
        $health['checks']['payment_gateways'] = [
            'status' => $activeGateways > 0 ? 'healthy' : 'warning',
            'message' => "{$activeGateways} active payment gateway(s)",
            'active_gateways' => $activeGateways,
            'last_check' => now()->toISOString(),
        ];

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
    private function getRecentActivity(): array
    {
        $activities = [];

        // Recent payments
        $recentPayments = Payment::with('customer')
            ->where('created_at', '>=', now()->subHours(24))
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        foreach ($recentPayments as $payment) {
            $activities[] = [
                'type' => 'payment',
                'title' => 'Payment Received',
                'description' => "Payment of {$payment->currency} {$payment->amount} from {$payment->customer->name}",
                'timestamp' => $payment->created_at->toISOString(),
                'status' => $payment->status,
                'icon' => 'credit-card',
                'color' => $payment->status === 'completed' ? 'green' : 'yellow',
            ];
        }

        // Recent vouchers
        $recentVouchers = Voucher::with('customer')
            ->where('created_at', '>=', now()->subHours(24))
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        foreach ($recentVouchers as $voucher) {
            $activities[] = [
                'type' => 'voucher',
                'title' => 'Voucher Generated',
                'description' => "Voucher {$voucher->code} created" . ($voucher->customer ? " for {$voucher->customer->name}" : ""),
                'timestamp' => $voucher->created_at->toISOString(),
                'status' => $voucher->status,
                'icon' => 'ticket',
                'color' => 'blue',
            ];
        }

        // Recent customers
        $recentCustomers = Customer::where('created_at', '>=', now()->subHours(24))
            ->orderBy('created_at', 'desc')
            ->limit(3)
            ->get();

        foreach ($recentCustomers as $customer) {
            $activities[] = [
                'type' => 'customer',
                'title' => 'New Customer',
                'description' => "Customer {$customer->name} registered",
                'timestamp' => $customer->created_at->toISOString(),
                'status' => $customer->status,
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
     * Get revenue analytics for charts
     */
    private function getRevenueAnalytics(): array
    {
        $days = [];
        $revenues = [];

        // Get last 30 days of revenue data
        for ($i = 29; $i >= 0; $i--) {
            $date = Carbon::now()->subDays($i);
            $dayRevenue = Payment::where('status', 'completed')
                ->whereDate('processed_at', $date)
                ->sum('amount');

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
