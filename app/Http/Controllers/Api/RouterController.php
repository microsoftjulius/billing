<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Services\Router\MikrotikService;
use App\Models\Voucher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RouterController extends Controller
{
    private MikrotikService $mikrotikService;
    private ?Tenant $currentTenant;

    public function __construct(MikrotikService $mikrotikService)
    {
        $this->mikrotikService = $mikrotikService;
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
     * Get router status and statistics
     */
    public function status(Request $request): JsonResponse
    {
        try {
            // Test connection first
            $connectionTest = $this->mikrotikService->testConnection();

            if (!$connectionTest) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot connect to MikroTik router',
                    'connected' => false,
                    'data' => [
                        'connection_status' => 'disconnected',
                        'last_check' => now()->toISOString(),
                        'error' => 'Failed to establish connection with router'
                    ]
                ], 503);
            }

            // Get system resources
            $resources = $this->mikrotikService->getSystemResources();

            if (empty($resources)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to retrieve router status',
                    'connected' => true,
                    'data' => [
                        'connection_status' => 'connected',
                        'data_status' => 'unavailable',
                        'last_check' => now()->toISOString()
                    ]
                ], 500);
            }

            // Get additional statistics from database
            $voucherStats = $this->getVoucherStats();
            $recentActivity = $this->getRecentActivity();

            $response = [
                'success' => true,
                'connected' => true,
                'message' => 'Router is online and responsive',
                'data' => [
                    'connection_status' => 'connected',
                    'last_check' => now()->toISOString(),
                    'system' => $resources,
                    'vouchers' => $voucherStats,
                    'recent_activity' => $recentActivity,
                    'summary' => [
                        'status' => $resources['health_status'] ?? 'unknown',
                        'is_healthy' => $resources['is_healthy'] ?? false,
                        'uptime_formatted' => $resources['uptime_formatted'] ?? 'unknown',
                        'active_users' => $resources['active_users'] ?? 0,
                        'total_users' => $resources['total_users'] ?? 0,
                        'cpu_load' => $resources['cpu_load_formatted'] ?? '0%',
                        'memory_usage' => $resources['memory_usage_formatted'] ?? '0%',
                        'disk_usage' => $resources['disk_usage_formatted'] ?? '0%',
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

            // Add debug info if requested
            if ($request->has('debug') && $request->get('debug') === 'true') {
                $response['debug'] = [
                    'config' => [
                        'host' => config('mikrotik.host'),
                        'port' => config('mikrotik.port'),
                        'timeout' => config('mikrotik.timeout'),
                    ],
                    'cache_keys' => [
                        'system_resources' => 'mikrotik.system.resources',
                        'active_users' => 'mikrotik.active_users',
                        'total_users' => 'mikrotik.total_users',
                    ]
                ];
            }

            return response()->json($response);

        } catch (\Exception $e) {
            Log::channel('router')->error('Failed to get router status', [
                'tenant_id' => $this->currentTenant?->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to get router status',
                'error' => config('app.debug') ? $e->getMessage() : null,
                'connected' => false,
                'data' => [
                    'connection_status' => 'error',
                    'last_check' => now()->toISOString(),
                    'error_details' => config('app.debug') ? $e->getMessage() : 'Internal server error'
                ]
            ], 500);
        }
    }

    /**
     * Get active users on the router
     */
    public function activeUsers(Request $request): JsonResponse
    {
        try {
            $limit = min($request->get('limit', 50), 200);
            $page = $request->get('page', 1);

            // Get active users from router
            $activeUsers = $this->mikrotikService->getActiveUsers();
            $totalUsers = count($activeUsers);

            // Paginate the results
            $offset = ($page - 1) * $limit;
            $paginatedUsers = array_slice($activeUsers, $offset, $limit);

            // Get additional info from database for each user
            $usersWithDetails = [];
            foreach ($paginatedUsers as $user) {
                $voucher = Voucher::where('code', $user['username'])->first();

                $userDetails = [
                    'username' => $user['username'],
                    'ip_address' => $user['address'],
                    'mac_address' => $user['mac_address'],
                    'uptime' => $user['uptime'],
                    'bytes_in' => $user['bytes_in'],
                    'bytes_out' => $user['bytes_out'],
                    'server' => $user['server'],
                    'connected_at' => $user['login_time'],
                    'session_time' => $user['uptime_seconds'],
                    'download_speed' => $user['download_speed_mbps'],
                    'upload_speed' => $user['upload_speed_mbps'],
                    'data_used' => $user['data_used_mb'],
                ];

                if ($voucher) {
                    $userDetails['voucher'] = [
                        'id' => $voucher->id,
                        'profile' => $voucher->profile,
                        'customer' => $voucher->customer ? [
                            'name' => $voucher->customer->name,
                            'phone' => $voucher->customer->phone,
                        ] : null,
                        'payment' => $voucher->payment ? [
                            'transaction_id' => $voucher->payment->transaction_id,
                            'amount' => $voucher->payment->amount,
                        ] : null,
                        'expires_at' => $voucher->expires_at?->toISOString(),
                    ];
                }

                // Add tenant context if tenant exists
                if ($this->currentTenant && $voucher) {
                    $userDetails['tenant'] = [
                        'id' => $this->currentTenant->id,
                        'code' => $this->currentTenant->code,
                        'name' => $this->currentTenant->name,
                    ];
                }

                $usersWithDetails[] = $userDetails;
            }

            $response = [
                'success' => true,
                'data' => [
                    'users' => $usersWithDetails,
                    'pagination' => [
                        'total' => $totalUsers,
                        'per_page' => $limit,
                        'current_page' => $page,
                        'last_page' => ceil($totalUsers / $limit),
                        'from' => $offset + 1,
                        'to' => min($offset + $limit, $totalUsers),
                    ],
                    'summary' => [
                        'total_active_users' => $totalUsers,
                        'total_bytes_in' => array_sum(array_column($activeUsers, 'bytes_in')),
                        'total_bytes_out' => array_sum(array_column($activeUsers, 'bytes_out')),
                        'avg_session_time' => $totalUsers > 0
                            ? round(array_sum(array_column($activeUsers, 'uptime_seconds')) / $totalUsers)
                            : 0,
                        'total_data_used_mb' => array_sum(array_column($activeUsers, 'data_used_mb')),
                        'max_concurrent_users' => $this->getMaxConcurrentUsers(),
                    ]
                ]
            ];

            return response()->json($response);

        } catch (\Exception $e) {
            Log::channel('router')->error('Failed to get active users', [
                'tenant_id' => $this->currentTenant?->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to get active users',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Get system resources (detailed)
     */
    public function systemResources(Request $request): JsonResponse
    {
        try {
            // Get detailed system resources
            $resources = $this->mikrotikService->getSystemResources();

            if (empty($resources)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to retrieve system resources'
                ], 500);
            }

            // Get additional historical data
            $historicalData = $this->getHistoricalResources();

            $response = [
                'success' => true,
                'data' => [
                    'current' => $resources,
                    'historical' => $historicalData,
                    'alerts' => $this->checkResourceAlerts($resources),
                    'recommendations' => $this->getResourceRecommendations($resources),
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
            Log::channel('router')->error('Failed to get system resources', [
                'tenant_id' => $this->currentTenant?->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to get system resources',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Get interface statistics
     */
    public function interfaces(Request $request): JsonResponse
    {
        try {
            $interfaces = $this->mikrotikService->getInterfaceStats();

            if (empty($interfaces)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to retrieve interface statistics'
                ], 500);
            }

            // Filter interfaces if requested
            $interfaceType = $request->get('type');
            if ($interfaceType) {
                $interfaces = array_filter($interfaces, function ($interface) use ($interfaceType) {
                    return strtolower($interface['type']) === strtolower($interfaceType);
                });
            }

            // Calculate totals
            $totalRxBytes = array_sum(array_column($interfaces, 'rx_bytes'));
            $totalTxBytes = array_sum(array_column($interfaces, 'tx_bytes'));
            $activeInterfaces = array_filter($interfaces, function ($interface) {
                return $interface['running'] === true;
            });

            $response = [
                'success' => true,
                'data' => [
                    'interfaces' => array_values($interfaces),
                    'summary' => [
                        'total_interfaces' => count($interfaces),
                        'active_interfaces' => count($activeInterfaces),
                        'total_rx_bytes' => $totalRxBytes,
                        'total_tx_bytes' => $totalTxBytes,
                        'total_traffic_gb' => round(($totalRxBytes + $totalTxBytes) / (1024 * 1024 * 1024), 2),
                        'most_active_interface' => $this->getMostActiveInterface($interfaces),
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

        } catch (\Exception $e) {
            Log::channel('router')->error('Failed to get interface statistics', [
                'tenant_id' => $this->currentTenant?->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to get interface statistics',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Get hotspot profiles
     */
    public function hotspotProfiles(Request $request): JsonResponse
    {
        try {
            $profiles = $this->mikrotikService->getHotspotProfiles();

            if (empty($profiles)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to retrieve hotspot profiles'
                ], 500);
            }

            // Get voucher counts per profile from database
            $profileUsage = [];
            foreach ($profiles as $profile) {
                $profileName = $profile['name'];
                $voucherCount = Voucher::where('profile', $profileName)
                    ->when($this->currentTenant, function ($query) {
                        $query->where('tenant_id', $this->currentTenant->id);
                    })
                    ->count();

                $activeVoucherCount = Voucher::where('profile', $profileName)
                    ->active()
                    ->when($this->currentTenant, function ($query) {
                        $query->where('tenant_id', $this->currentTenant->id);
                    })
                    ->count();

                $profileUsage[$profileName] = [
                    'total_vouchers' => $voucherCount,
                    'active_vouchers' => $activeVoucherCount,
                    'usage_percentage' => $voucherCount > 0 ? round(($activeVoucherCount / $voucherCount) * 100, 2) : 0,
                ];
            }

            // Merge profile data with usage
            $profilesWithUsage = array_map(function ($profile) use ($profileUsage) {
                $profileName = $profile['name'];
                $usage = $profileUsage[$profileName] ?? [
                    'total_vouchers' => 0,
                    'active_vouchers' => 0,
                    'usage_percentage' => 0,
                ];

                return array_merge($profile, $usage);
            }, $profiles);

            $response = [
                'success' => true,
                'data' => [
                    'profiles' => $profilesWithUsage,
                    'summary' => [
                        'total_profiles' => count($profiles),
                        'total_vouchers' => array_sum(array_column($profileUsage, 'total_vouchers')),
                        'active_vouchers' => array_sum(array_column($profileUsage, 'active_vouchers')),
                        'most_used_profile' => $this->getMostUsedProfile($profileUsage),
                        'least_used_profile' => $this->getLeastUsedProfile($profileUsage),
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

        } catch (\Exception $e) {
            Log::channel('router')->error('Failed to get hotspot profiles', [
                'tenant_id' => $this->currentTenant?->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to get hotspot profiles',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Sync vouchers between database and router
     */
    public function syncVouchers(Request $request): JsonResponse
    {
        try {
            // Validate request
            $syncType = $request->get('type', 'all'); // all, missing, disabled, expired

            DB::beginTransaction();

            $results = [
                'total_processed' => 0,
                'synced' => 0,
                'failed' => 0,
                'details' => []
            ];

            // Get vouchers based on sync type
            $query = Voucher::query();

            if ($this->currentTenant) {
                $query->where('tenant_id', $this->currentTenant->id);
            }

            switch ($syncType) {
                case 'missing':
                    // Get vouchers that exist in DB but not on router
                    $vouchers = $query->get();
                    foreach ($vouchers as $voucher) {
                        $routerUser = $this->mikrotikService->getUser($voucher->code);
                        if (!$routerUser) {
                            $results = $this->syncSingleVoucher($voucher, $results);
                        }
                    }
                    break;

                case 'disabled':
                    // Sync disabled vouchers
                    $vouchers = $query->where('status', 'disabled')->get();
                    foreach ($vouchers as $voucher) {
                        $results = $this->syncSingleVoucher($voucher, $results);
                    }
                    break;

                case 'expired':
                    // Sync expired vouchers
                    $vouchers = $query->where('expires_at', '<', now())->get();
                    foreach ($vouchers as $voucher) {
                        $results = $this->syncSingleVoucher($voucher, $results);
                    }
                    break;

                case 'all':
                default:
                    // Sync all vouchers
                    $vouchers = $query->get();
                    foreach ($vouchers as $voucher) {
                        $results = $this->syncSingleVoucher($voucher, $results);
                    }
                    break;
            }

            DB::commit();

            $logData = [
                'sync_type' => $syncType,
                'results' => $results,
                'tenant_id' => $this->currentTenant?->id,
                'initiated_by' => auth()->id() ?? 'system',
            ];

            if ($results['failed'] > 0) {
                Log::channel('router')->warning('Voucher sync completed with errors', $logData);
            } else {
                Log::channel('router')->info('Voucher sync completed successfully', $logData);
            }

            return response()->json([
                'success' => $results['failed'] === 0,
                'message' => $results['failed'] === 0
                    ? 'Voucher sync completed successfully'
                    : 'Voucher sync completed with some errors',
                'data' => $results
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            Log::channel('router')->error('Failed to sync vouchers', [
                'sync_type' => $request->get('type'),
                'tenant_id' => $this->currentTenant?->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to sync vouchers: ' . $e->getMessage(),
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Cleanup expired users from router
     */
    public function cleanupExpired(Request $request): JsonResponse
    {
        try {
            // Get count of expired vouchers in database
            $expiredInDb = Voucher::where('expires_at', '<', now())
                ->when($this->currentTenant, function ($query) {
                    $query->where('tenant_id', $this->currentTenant->id);
                })
                ->count();

            // Remove expired users from router
            $removedCount = $this->mikrotikService->removeExpiredUsers();

            // Clean up expired vouchers in database if they don't have payments
            $cleanupDb = $request->get('cleanup_database', false);
            $dbCleaned = 0;

            if ($cleanupDb) {
                $dbCleaned = Voucher::where('expires_at', '<', now())
                    ->whereDoesntHave('payment')
                    ->when($this->currentTenant, function ($query) {
                        $query->where('tenant_id', $this->currentTenant->id);
                    })
                    ->delete();
            }

            $response = [
                'success' => true,
                'message' => 'Cleanup completed successfully',
                'data' => [
                    'router_cleanup' => [
                        'removed_users' => $removedCount,
                        'status' => $removedCount > 0 ? 'cleaned' : 'no_action',
                    ],
                    'database_stats' => [
                        'expired_vouchers' => $expiredInDb,
                        'cleaned_vouchers' => $cleanupDb ? $dbCleaned : 0,
                        'cleanup_performed' => $cleanupDb,
                    ],
                    'recommendations' => $this->getCleanupRecommendations($expiredInDb, $removedCount),
                ]
            ];

            Log::channel('router')->info('Router cleanup completed', [
                'removed_users' => $removedCount,
                'db_cleaned' => $dbCleaned,
                'expired_in_db' => $expiredInDb,
                'tenant_id' => $this->currentTenant?->id,
                'cleanup_by' => auth()->id() ?? 'system',
            ]);

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
            Log::channel('router')->error('Failed to cleanup expired users', [
                'tenant_id' => $this->currentTenant?->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to cleanup expired users',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Helper: Sync single voucher with router
     */
    private function syncSingleVoucher(Voucher $voucher, array $results): array
    {
        $results['total_processed']++;

        try {
            $routerUser = $this->mikrotikService->getUser($voucher->code);
            $isActiveOnRouter = $routerUser ? ($routerUser['disabled'] === 'false') : false;
            $isActiveInDb = $voucher->getIsActiveAttribute();

            if (!$routerUser) {
                // Voucher missing on router, recreate it
                $voucherDTO = \App\DTOs\Router\VoucherDTO::fromArray($voucher->toArray());
                $created = $this->mikrotikService->createVoucher($voucherDTO);

                if ($created) {
                    $results['synced']++;
                    $results['details'][] = [
                        'code' => $voucher->code,
                        'action' => 'created',
                        'status' => 'success'
                    ];
                } else {
                    $results['failed']++;
                    $results['details'][] = [
                        'code' => $voucher->code,
                        'action' => 'create',
                        'status' => 'failed',
                        'error' => 'Failed to create voucher on router'
                    ];
                }
            } elseif ($isActiveOnRouter !== $isActiveInDb) {
                // Status mismatch
                if (!$isActiveInDb) {
                    // Disable on router
                    $disabled = $this->mikrotikService->disableVoucher($voucher->code);

                    if ($disabled) {
                        $results['synced']++;
                        $results['details'][] = [
                            'code' => $voucher->code,
                            'action' => 'disabled',
                            'status' => 'success'
                        ];
                    } else {
                        $results['failed']++;
                        $results['details'][] = [
                            'code' => $voucher->code,
                            'action' => 'disable',
                            'status' => 'failed',
                            'error' => 'Failed to disable voucher on router'
                        ];
                    }
                }
            } else {
                // Already in sync
                $results['details'][] = [
                    'code' => $voucher->code,
                    'action' => 'skipped',
                    'status' => 'already_synced'
                ];
            }

        } catch (\Exception $e) {
            $results['failed']++;
            $results['details'][] = [
                'code' => $voucher->code,
                'action' => 'sync',
                'status' => 'failed',
                'error' => $e->getMessage()
            ];
        }

        return $results;
    }

    /**
     * Helper: Get voucher statistics
     */
    private function getVoucherStats(): array
    {
        $query = Voucher::query();

        if ($this->currentTenant) {
            $query->where('tenant_id', $this->currentTenant->id);
        }

        return [
            'total' => $query->count(),
            'active' => (clone $query)->active()->count(),
            'expired' => (clone $query)->expired()->count(),
            'disabled' => (clone $query)->where('status', 'disabled')->count(),
            'today' => (clone $query)->whereDate('created_at', today())->count(),
            'week' => (clone $query)->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()])->count(),
            'month' => (clone $query)->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()])->count(),
        ];
    }

    /**
     * Helper: Get recent activity
     */
    private function getRecentActivity(): array
    {
        $query = Voucher::with(['customer', 'payment'])
            ->orderBy('created_at', 'desc')
            ->limit(10);

        if ($this->currentTenant) {
            $query->where('tenant_id', $this->currentTenant->id);
        }

        return $query->get()->map(function ($voucher) {
            return [
                'code' => $voucher->code,
                'profile' => $voucher->profile,
                'customer' => $voucher->customer ? $voucher->customer->name : 'Unknown',
                'created_at' => $voucher->created_at->toISOString(),
                'expires_at' => $voucher->expires_at?->toISOString(),
                'status' => $voucher->status,
            ];
        })->toArray();
    }

    /**
     * Helper: Get max concurrent users (from historical data)
     */
    private function getMaxConcurrentUsers(): int
    {
        // This would ideally come from historical monitoring data
        // For now, we'll return a reasonable estimate based on current users
        $activeUsers = $this->mikrotikService->getActiveUsersCount();
        return (int) ($activeUsers * 1.5); // 50% more than current
    }

    /**
     * Helper: Get historical resource data
     */
    private function getHistoricalResources(): array
    {
        // This would ideally come from a monitoring database
        // For now, return dummy data
        return [
            'cpu_trend' => $this->generateTrendData(80, 95),
            'memory_trend' => $this->generateTrendData(60, 85),
            'users_trend' => $this->generateTrendData(10, 50),
            'bandwidth_trend' => $this->generateTrendData(100, 1000),
        ];
    }

    /**
     * Helper: Generate trend data
     */
    private function generateTrendData(int $min, int $max): array
    {
        $trend = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subHours($i * 4)->format('Y-m-d H:i');
            $value = rand($min, $max);
            $trend[] = ['time' => $date, 'value' => $value];
        }
        return $trend;
    }

    /**
     * Helper: Check for resource alerts
     */
    private function checkResourceAlerts(array $resources): array
    {
        $alerts = [];

        if (($resources['cpu_load'] ?? 0) > 80) {
            $alerts[] = [
                'level' => 'warning',
                'message' => 'High CPU usage detected',
                'metric' => 'cpu_load',
                'value' => $resources['cpu_load'],
                'threshold' => 80,
            ];
        }

        if (($resources['memory_usage'] ?? 0) > 85) {
            $alerts[] = [
                'level' => 'warning',
                'message' => 'High memory usage detected',
                'metric' => 'memory_usage',
                'value' => $resources['memory_usage'],
                'threshold' => 85,
            ];
        }

        if (($resources['disk_usage'] ?? 0) > 90) {
            $alerts[] = [
                'level' => 'critical',
                'message' => 'High disk usage detected',
                'metric' => 'disk_usage',
                'value' => $resources['disk_usage'],
                'threshold' => 90,
            ];
        }

        if (($resources['uptime_seconds'] ?? 0) < 3600) {
            $alerts[] = [
                'level' => 'info',
                'message' => 'Router recently rebooted',
                'metric' => 'uptime',
                'value' => $resources['uptime_formatted'],
                'threshold' => '1 hour',
            ];
        }

        return $alerts;
    }

    /**
     * Helper: Get resource recommendations
     */
    private function getResourceRecommendations(array $resources): array
    {
        $recommendations = [];

        if (($resources['cpu_load'] ?? 0) > 70) {
            $recommendations[] = 'Consider optimizing hotspot profiles to reduce CPU load';
        }

        if (($resources['memory_usage'] ?? 0) > 75) {
            $recommendations[] = 'Review active connections and consider removing idle users';
        }

        if (($resources['disk_usage'] ?? 0) > 80) {
            $recommendations[] = 'Clean up log files and consider increasing disk space';
        }

        if (($resources['active_users'] ?? 0) >= ($resources['total_users'] ?? 1) * 0.9) {
            $recommendations[] = 'High user concurrency, consider increasing capacity';
        }

        if (empty($recommendations)) {
            $recommendations[] = 'System resources are within optimal ranges';
        }

        return $recommendations;
    }

    /**
     * Helper: Get most active interface
     */
    private function getMostActiveInterface(array $interfaces): ?array
    {
        if (empty($interfaces)) {
            return null;
        }

        $maxTraffic = 0;
        $mostActive = null;

        foreach ($interfaces as $interface) {
            $traffic = ($interface['rx_bytes'] ?? 0) + ($interface['tx_bytes'] ?? 0);
            if ($traffic > $maxTraffic) {
                $maxTraffic = $traffic;
                $mostActive = $interface;
            }
        }

        return $mostActive;
    }

    /**
     * Helper: Get most used profile
     */
    private function getMostUsedProfile(array $profileUsage): ?array
    {
        if (empty($profileUsage)) {
            return null;
        }

        $maxUsage = 0;
        $mostUsed = null;

        foreach ($profileUsage as $profileName => $usage) {
            if ($usage['total_vouchers'] > $maxUsage) {
                $maxUsage = $usage['total_vouchers'];
                $mostUsed = [
                    'name' => $profileName,
                    ...$usage
                ];
            }
        }

        return $mostUsed;
    }

    /**
     * Helper: Get least used profile
     */
    private function getLeastUsedProfile(array $profileUsage): ?array
    {
        if (empty($profileUsage)) {
            return null;
        }

        $minUsage = PHP_INT_MAX;
        $leastUsed = null;

        foreach ($profileUsage as $profileName => $usage) {
            if ($usage['total_vouchers'] < $minUsage && $usage['total_vouchers'] > 0) {
                $minUsage = $usage['total_vouchers'];
                $leastUsed = [
                    'name' => $profileName,
                    ...$usage
                ];
            }
        }

        return $leastUsed;
    }

    /**
     * Helper: Get cleanup recommendations
     */
    private function getCleanupRecommendations(int $expiredCount, int $removedCount): array
    {
        $recommendations = [];

        if ($expiredCount > 100) {
            $recommendations[] = 'Many expired vouchers detected. Consider increasing cleanup frequency.';
        }

        if ($removedCount > 50) {
            $recommendations[] = 'Successfully cleaned up expired users. Consider automating this process.';
        }

        if ($expiredCount > 0 && $removedCount === 0) {
            $recommendations[] = 'Expired vouchers found but none removed from router. Check router permissions.';
        }

        if (empty($recommendations)) {
            $recommendations[] = 'Cleanup process completed successfully. No issues detected.';
        }

        return $recommendations;
    }
}
