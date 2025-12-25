<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\SmsLog;
use App\Services\SmsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Cache;

class SmsController extends Controller
{
    private SmsService $smsService;
    private ?Tenant $currentTenant;

    public function __construct(SmsService $smsService)
    {
        $this->smsService = $smsService;
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
     * Get SMS balance
     */
    public function balance(Request $request): JsonResponse
    {
        try {
            // Check if user is authorized (admin or staff)
            if (!$this->isAuthorizedUser()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized. Admin or staff access required.'
                ], 403);
            }

            // Check SMS service status
            $smsSettings = $this->getSmsSettings();

            if (!$smsSettings['enabled']) {
                return response()->json([
                    'success' => false,
                    'message' => 'SMS service is disabled',
                    'data' => [
                        'service_status' => 'disabled',
                        'balance' => 0,
                        'currency' => 'UGX',
                    ]
                ], 400);
            }

            // Get balance from service
            $balance = $this->smsService->checkBalance();

            // Calculate estimated messages remaining
            $estimatedMessages = $this->calculateEstimatedMessages($balance);

            // Get recent usage statistics
            $usageStats = $this->getSmsUsageStats();

            // Check if balance is low
            $isLowBalance = $balance < config('services.sms.low_balance_threshold', 5000);
            $lowBalanceThreshold = config('services.sms.low_balance_threshold', 5000);

            // Prepare response
            $response = [
                'success' => true,
                'data' => [
                    'balance' => $balance,
                    'currency' => 'UGX',
                    'formatted_balance' => number_format($balance) . ' UGX',
                    'estimated_messages_remaining' => $estimatedMessages,
                    'low_balance_threshold' => $lowBalanceThreshold,
                    'is_low_balance' => $isLowBalance,
                    'service_status' => 'enabled',
                    'provider' => $smsSettings['provider'] ?? 'unknown',
                    'usage_statistics' => $usageStats,
                    'last_checked' => now()->toISOString(),
                    'recommendations' => $this->getBalanceRecommendations($balance, $estimatedMessages, $usageStats),
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

            // Log balance check
            Log::channel('sms')->info('SMS balance checked', [
                'tenant_id' => $this->currentTenant?->id,
                'balance' => $balance,
                'estimated_messages' => $estimatedMessages,
                'checked_by' => auth()->id() ?? 'system',
            ]);

            // Trigger low balance alert if needed
            if ($isLowBalance) {
                $this->triggerLowBalanceAlert($balance);
            }

            return response()->json($response);

        } catch (\Exception $e) {
            Log::channel('sms')->error('Failed to get SMS balance', [
                'tenant_id' => $this->currentTenant?->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to get SMS balance',
                'error' => config('app.debug') ? $e->getMessage() : null,
                'data' => [
                    'service_status' => 'error',
                    'last_checked' => now()->toISOString(),
                ]
            ], 500);
        }
    }

    /**
     * Get SMS logs
     */
    public function logs(Request $request): JsonResponse
    {
        try {
            // Check if user is authorized (admin or staff)
            if (!$this->isAuthorizedUser()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized. Admin or staff access required.'
                ], 403);
            }

            $perPage = min($request->get('per_page', 15), 100);
            $page = $request->get('page', 1);

            $query = SmsLog::query()
                ->where('tenant_id', $this->currentTenant->id)
                ->orderBy('created_at', 'desc');

            // Apply filters
            if ($request->has('status')) {
                $query->where('status', $request->get('status'));
            }

            if ($request->has('recipient')) {
                $query->where('recipient', 'LIKE', '%' . $request->get('recipient') . '%');
            }

            if ($request->has('message_type')) {
                $query->where('message_type', $request->get('message_type'));
            }

            if ($request->has('start_date')) {
                $query->whereDate('created_at', '>=', $request->get('start_date'));
            }

            if ($request->has('end_date')) {
                $query->whereDate('created_at', '<=', $request->get('end_date'));
            }

            if ($request->has('message_id')) {
                $query->where('provider_message_id', $request->get('message_id'));
            }

            if ($request->has('search')) {
                $search = $request->get('search');
                $query->where(function ($q) use ($search) {
                    $q->where('recipient', 'LIKE', '%' . $search . '%')
                        ->orWhere('message', 'LIKE', '%' . $search . '%')
                        ->orWhere('provider_message_id', 'LIKE', '%' . $search . '%');
                });
            }

            $logs = $query->paginate($perPage, ['*'], 'page', $page);

            // Get statistics for the filtered period
            $statistics = $this->getLogStatistics($query);

            // Prepare response
            $response = [
                'success' => true,
                'tenant' => [
                    'id' => $this->currentTenant->id,
                    'code' => $this->currentTenant->code,
                    'name' => $this->currentTenant->name,
                ],
                'data' => [
                    'logs' => $logs->map(function ($log) {
                        return $this->formatSmsLogResponse($log);
                    })->toArray(),
                    'pagination' => [
                        'total' => $logs->total(),
                        'per_page' => $logs->perPage(),
                        'current_page' => $logs->currentPage(),
                        'last_page' => $logs->lastPage(),
                        'from' => $logs->firstItem(),
                        'to' => $logs->lastItem(),
                    ],
                    'statistics' => $statistics,
                    'summary' => [
                        'total_sent' => $logs->total(),
                        'successful_count' => $logs->where('status', 'sent')->count(),
                        'failed_count' => $logs->where('status', 'failed')->count(),
                        'pending_count' => $logs->where('status', 'pending')->count(),
                        'total_cost' => $logs->sum('cost'),
                        'average_cost_per_sms' => $logs->count() > 0
                            ? round($logs->sum('cost') / $logs->count(), 2)
                            : 0,
                    ],
                    'filters_applied' => $request->all(),
                ]
            ];

            // Add debug info if requested
            if ($request->has('debug') && $request->get('debug') === 'true') {
                $response['debug'] = [
                    'query' => $query->toSql(),
                    'bindings' => $query->getBindings(),
                    'sms_settings' => $this->getSmsSettings(),
                ];
            }

            return response()->json($response);

        } catch (\Exception $e) {
            Log::channel('sms')->error('Failed to fetch SMS logs', [
                'tenant_id' => $this->currentTenant?->id,
                'error' => $e->getMessage(),
                'filters' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch SMS logs',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Check if user is authorized
     */
    private function isAuthorizedUser(): bool
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

        // Check if user has admin or staff role for this tenant
        return $user->hasRole('admin') || $user->hasRole('staff') || $user->hasRole('super-admin');
    }

    /**
     * Get SMS settings for the tenant
     */
    private function getSmsSettings(): array
    {
        return Cache::remember('tenant_sms_settings_' . $this->currentTenant->id, 300, function () {
            // This would typically come from a TenantSetting model
            // For now, we'll return a simplified version
            return [
                'enabled' => true, // Should come from database
                'provider' => 'ugsms', // Should come from database
                'api_key' => config('services.ugsms.api_key'),
                'base_url' => config('services.ugsms.base_url'),
                'sender_id' => config('services.ugsms.sender_id', 'BILLING'),
            ];
        });
    }

    /**
     * Calculate estimated messages remaining
     */
    private function calculateEstimatedMessages(float $balance): int
    {
        // Assuming average cost per SMS is 20 UGX
        $averageCostPerSms = 20;

        if ($averageCostPerSms <= 0) {
            return 0;
        }

        return (int) floor($balance / $averageCostPerSms);
    }

    /**
     * Get SMS usage statistics
     */
    private function getSmsUsageStats(): array
    {
        $today = now()->startOfDay();
        $yesterday = now()->subDay()->startOfDay();
        $thisMonth = now()->startOfMonth();
        $lastMonth = now()->subMonth()->startOfMonth();

        return [
            'today' => [
                'sent' => SmsLog::where('tenant_id', $this->currentTenant->id)
                    ->where('created_at', '>=', $today)
                    ->where('status', 'sent')
                    ->count(),
                'failed' => SmsLog::where('tenant_id', $this->currentTenant->id)
                    ->where('created_at', '>=', $today)
                    ->where('status', 'failed')
                    ->count(),
                'cost' => SmsLog::where('tenant_id', $this->currentTenant->id)
                    ->where('created_at', '>=', $today)
                    ->sum('cost'),
            ],
            'yesterday' => [
                'sent' => SmsLog::where('tenant_id', $this->currentTenant->id)
                    ->whereBetween('created_at', [$yesterday, $today])
                    ->where('status', 'sent')
                    ->count(),
                'failed' => SmsLog::where('tenant_id', $this->currentTenant->id)
                    ->whereBetween('created_at', [$yesterday, $today])
                    ->where('status', 'failed')
                    ->count(),
                'cost' => SmsLog::where('tenant_id', $this->currentTenant->id)
                    ->whereBetween('created_at', [$yesterday, $today])
                    ->sum('cost'),
            ],
            'this_month' => [
                'sent' => SmsLog::where('tenant_id', $this->currentTenant->id)
                    ->where('created_at', '>=', $thisMonth)
                    ->where('status', 'sent')
                    ->count(),
                'failed' => SmsLog::where('tenant_id', $this->currentTenant->id)
                    ->where('created_at', '>=', $thisMonth)
                    ->where('status', 'failed')
                    ->count(),
                'cost' => SmsLog::where('tenant_id', $this->currentTenant->id)
                    ->where('created_at', '>=', $thisMonth)
                    ->sum('cost'),
                'average_daily' => SmsLog::where('tenant_id', $this->currentTenant->id)
                        ->where('created_at', '>=', $thisMonth)
                        ->where('status', 'sent')
                        ->count() / now()->day,
            ],
            'last_month' => [
                'sent' => SmsLog::where('tenant_id', $this->currentTenant->id)
                    ->whereBetween('created_at', [$lastMonth, $thisMonth])
                    ->where('status', 'sent')
                    ->count(),
                'failed' => SmsLog::where('tenant_id', $this->currentTenant->id)
                    ->whereBetween('created_at', [$lastMonth, $thisMonth])
                    ->where('status', 'failed')
                    ->count(),
                'cost' => SmsLog::where('tenant_id', $this->currentTenant->id)
                    ->whereBetween('created_at', [$lastMonth, $thisMonth])
                    ->sum('cost'),
                'average_daily' => SmsLog::where('tenant_id', $this->currentTenant->id)
                        ->whereBetween('created_at', [$lastMonth, $thisMonth])
                        ->where('status', 'sent')
                        ->count() / 30,
            ],
            'total' => [
                'sent' => SmsLog::where('tenant_id', $this->currentTenant->id)
                    ->where('status', 'sent')
                    ->count(),
                'failed' => SmsLog::where('tenant_id', $this->currentTenant->id)
                    ->where('status', 'failed')
                    ->count(),
                'cost' => SmsLog::where('tenant_id', $this->currentTenant->id)
                    ->sum('cost'),
                'success_rate' => function() {
                    $total = SmsLog::where('tenant_id', $this->currentTenant->id)->count();
                    $sent = SmsLog::where('tenant_id', $this->currentTenant->id)
                        ->where('status', 'sent')
                        ->count();
                    return $total > 0 ? round(($sent / $total) * 100, 2) : 0;
                },
            ],
        ];
    }

    /**
     * Get balance recommendations
     */
    private function getBalanceRecommendations(float $balance, int $estimatedMessages, array $usageStats): array
    {
        $recommendations = [];

        // Check if balance is critically low
        if ($balance < 1000) {
            $recommendations[] = [
                'priority' => 'high',
                'message' => 'SMS balance is critically low. Please top up immediately to avoid service disruption.',
                'suggested_topup' => 50000, // 50,000 UGX
            ];
        }
        // Check if balance is low
        elseif ($balance < 5000) {
            $recommendations[] = [
                'priority' => 'medium',
                'message' => 'SMS balance is low. Consider topping up soon.',
                'suggested_topup' => 20000, // 20,000 UGX
            ];
        }

        // Check usage patterns
        $dailyAverage = $usageStats['this_month']['average_daily'] ?? 0;
        $daysRemaining = $estimatedMessages > 0 ? floor($estimatedMessages / max($dailyAverage, 1)) : 0;

        if ($dailyAverage > 0 && $daysRemaining < 7) {
            $recommendations[] = [
                'priority' => 'medium',
                'message' => "Based on current usage, SMS balance will last approximately {$daysRemaining} days.",
                'suggested_topup' => $dailyAverage * 20 * 30, // Estimate for 30 days
            ];
        }

        // Check if success rate is low
        $successRate = $usageStats['total']['success_rate'] ?? 0;
        if ($successRate < 90 && $successRate > 0) {
            $recommendations[] = [
                'priority' => 'low',
                'message' => "SMS success rate is {$successRate}%. Consider checking recipient numbers and message content.",
            ];
        }

        // If no issues, provide positive feedback
        if (empty($recommendations) && $balance > 10000) {
            $recommendations[] = [
                'priority' => 'info',
                'message' => 'SMS balance is healthy. Current balance should last for several weeks.',
            ];
        }

        return $recommendations;
    }

    /**
     * Trigger low balance alert
     */
    private function triggerLowBalanceAlert(float $balance): void
    {
        $lastAlertKey = 'sms_low_balance_alert_' . $this->currentTenant->id;
        $lastAlertTime = Cache::get($lastAlertKey);

        // Send alert only once per hour to avoid spam
        if (!$lastAlertTime || now()->diffInHours($lastAlertTime) >= 1) {
            try {
                $adminPhone = config('app.admin_phone');

                if ($adminPhone) {
                    $this->smsService->sendLowBalanceAlert($balance);
                    Cache::put($lastAlertKey, now(), 3600); // Cache for 1 hour

                    Log::channel('sms')->warning('Low balance alert sent', [
                        'tenant_id' => $this->currentTenant->id,
                        'balance' => $balance,
                        'admin_phone' => $adminPhone,
                    ]);
                }
            } catch (\Exception $e) {
                Log::channel('sms')->error('Failed to send low balance alert', [
                    'tenant_id' => $this->currentTenant->id,
                    'balance' => $balance,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Get log statistics
     */
    private function getLogStatistics($query): array
    {
        $totalLogs = (clone $query)->count();
        $successfulLogs = (clone $query)->where('status', 'sent')->count();
        $failedLogs = (clone $query)->where('status', 'failed')->count();
        $pendingLogs = (clone $query)->where('status', 'pending')->count();

        $totalCost = (clone $query)->sum('cost');
        $averageCost = $totalLogs > 0 ? round($totalCost / $totalLogs, 2) : 0;

        // Message type breakdown
        $messageTypes = (clone $query)
            ->select('message_type', \DB::raw('count(*) as count'), \DB::raw('sum(cost) as total_cost'))
            ->groupBy('message_type')
            ->orderByDesc('count')
            ->get()
            ->toArray();

        // Hourly distribution (last 24 hours)
        $hourlyDistribution = (clone $query)
            ->where('created_at', '>=', now()->subHours(24))
            ->selectRaw('HOUR(created_at) as hour, COUNT(*) as count')
            ->groupBy(\DB::raw('HOUR(created_at)'))
            ->orderBy('hour')
            ->get()
            ->mapWithKeys(function ($item) {
                return [$item->hour => $item->count];
            })
            ->toArray();

        // Top recipients
        $topRecipients = (clone $query)
            ->select('recipient', \DB::raw('count(*) as count'))
            ->groupBy('recipient')
            ->orderByDesc('count')
            ->limit(10)
            ->get()
            ->toArray();

        return [
            'totals' => [
                'all' => $totalLogs,
                'successful' => $successfulLogs,
                'failed' => $failedLogs,
                'pending' => $pendingLogs,
            ],
            'rates' => [
                'success_rate' => $totalLogs > 0 ? round(($successfulLogs / $totalLogs) * 100, 2) : 0,
                'failure_rate' => $totalLogs > 0 ? round(($failedLogs / $totalLogs) * 100, 2) : 0,
                'pending_rate' => $totalLogs > 0 ? round(($pendingLogs / $totalLogs) * 100, 2) : 0,
            ],
            'costs' => [
                'total_cost' => $totalCost,
                'average_cost_per_sms' => $averageCost,
                'estimated_monthly_cost' => round($averageCost * ($totalLogs / max(now()->diffInDays($query->min('created_at') ?? now()), 1)) * 30, 2),
            ],
            'breakdowns' => [
                'message_types' => $messageTypes,
                'hourly_distribution' => $hourlyDistribution,
                'top_recipients' => $topRecipients,
            ],
        ];
    }

    /**
     * Format SMS log response
     */
    private function formatSmsLogResponse(SmsLog $log): array
    {
        return [
            'id' => $log->id,
            'uuid' => $log->uuid,
            'recipient' => $log->recipient,
            'message' => $log->message,
            'message_type' => $log->message_type,
            'status' => $log->status,
            'cost' => $log->cost,
            'provider_message_id' => $log->provider_message_id,
            'provider_response' => $log->provider_response,
            'error_message' => $log->error_message,
            'retry_count' => $log->retry_count,
            'sent_at' => $log->sent_at?->toISOString(),
            'delivered_at' => $log->delivered_at?->toISOString(),
            'created_at' => $log->created_at->toISOString(),
            'updated_at' => $log->updated_at->toISOString(),
            'metadata' => $log->metadata,
            'related_entities' => [
                'voucher_id' => $log->voucher_id,
                'payment_id' => $log->payment_id,
                'customer_id' => $log->customer_id,
            ],
        ];
    }
}
