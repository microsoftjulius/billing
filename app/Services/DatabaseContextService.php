<?php

namespace App\Services;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DatabaseContextService
{
    /**
     * Get the appropriate database connection based on user context
     */
    public static function getConnection(?User $user = null): string
    {
        $user = $user ?? auth()->user();
        
        // If no user or user has no tenant_id, use central database
        if (!$user || !$user->tenant_id) {
            return 'pgsql_central';
        }
        
        // If user belongs to a tenant, use tenant database
        return 'pgsql';
    }
    
    /**
     * Check if current user is a global admin (no tenant_id)
     */
    public static function isGlobalAdmin(?User $user = null): bool
    {
        $user = $user ?? auth()->user();
        return $user && $user->role === 'admin' && !$user->tenant_id;
    }
    
    /**
     * Check if current user is a tenant admin
     */
    public static function isTenantAdmin(?User $user = null): bool
    {
        $user = $user ?? auth()->user();
        return $user && $user->role === 'admin' && $user->tenant_id;
    }
    
    /**
     * Get aggregated data from all tenants (for global admin)
     */
    public static function getAggregatedData(string $table, array $columns = ['*'], array $conditions = []): array
    {
        if (!self::isGlobalAdmin()) {
            throw new \Exception('Access denied. Global admin required.');
        }
        
        $results = [];
        
        // Get all tenants from central database
        $tenants = DB::connection('pgsql_central')
            ->table('tenants')
            ->where('is_active', true)
            ->get();
        
        foreach ($tenants as $tenant) {
            try {
                // Query each tenant's data
                $query = DB::connection('pgsql')
                    ->table($table)
                    ->select($columns)
                    ->where('tenant_id', $tenant->id);
                
                // Apply additional conditions
                foreach ($conditions as $column => $value) {
                    $query->where($column, $value);
                }
                
                $tenantData = $query->get()->toArray();
                
                // Add tenant information to each record
                foreach ($tenantData as $record) {
                    $record = (array) $record;
                    $record['tenant_name'] = $tenant->name;
                    $record['tenant_slug'] = $tenant->slug;
                    $record['tenant_plan'] = $tenant->plan;
                    $results[] = $record;
                }
                
            } catch (\Exception $e) {
                Log::warning("Failed to get data from tenant {$tenant->id}", [
                    'tenant' => $tenant->id,
                    'table' => $table,
                    'error' => $e->getMessage()
                ]);
            }
        }
        
        return $results;
    }
    
    /**
     * Get tenant-specific data
     */
    public static function getTenantData(string $table, array $columns = ['*'], array $conditions = [], ?string $tenantId = null): array
    {
        $user = auth()->user();
        
        if (!$user) {
            throw new \Exception('Authentication required.');
        }
        
        // Use provided tenant ID or user's tenant ID
        $tenantId = $tenantId ?? $user->tenant_id;
        
        if (!$tenantId) {
            throw new \Exception('Tenant context required.');
        }
        
        $query = DB::connection('pgsql')
            ->table($table)
            ->select($columns)
            ->where('tenant_id', $tenantId);
        
        // Apply additional conditions
        foreach ($conditions as $column => $value) {
            if (is_array($value)) {
                $query->whereIn($column, $value);
            } else {
                $query->where($column, $value);
            }
        }
        
        return $query->get()->toArray();
    }
    
    /**
     * Get dashboard statistics based on user context
     */
    public static function getDashboardStats(?User $user = null): array
    {
        $user = $user ?? auth()->user();
        
        if (self::isGlobalAdmin($user)) {
            return self::getGlobalDashboardStats();
        } else {
            return self::getTenantDashboardStats($user->tenant_id);
        }
    }
    
    /**
     * Get global dashboard statistics (all tenants aggregated)
     */
    private static function getGlobalDashboardStats(): array
    {
        $stats = [
            'tenants' => [],
            'totals' => [
                'customers' => 0,
                'payments' => 0,
                'vouchers' => 0,
                'revenue' => 0,
                'active_tenants' => 0,
            ],
            'by_plan' => [
                'starter' => 0,
                'professional' => 0,
                'enterprise' => 0,
            ]
        ];
        
        // Get tenant information from central database
        $tenants = DB::connection('pgsql_central')
            ->table('tenants')
            ->where('is_active', true)
            ->get();
        
        $stats['totals']['active_tenants'] = $tenants->count();
        
        foreach ($tenants as $tenant) {
            $stats['by_plan'][$tenant->plan]++;
            
            try {
                // Get tenant-specific stats
                $tenantStats = self::getTenantDashboardStats($tenant->id);
                
                $stats['tenants'][] = [
                    'id' => $tenant->id,
                    'name' => $tenant->name,
                    'slug' => $tenant->slug,
                    'plan' => $tenant->plan,
                    'stats' => $tenantStats
                ];
                
                // Aggregate totals
                $stats['totals']['customers'] += $tenantStats['customers']['total'] ?? 0;
                $stats['totals']['payments'] += $tenantStats['payments']['total'] ?? 0;
                $stats['totals']['vouchers'] += $tenantStats['vouchers']['total'] ?? 0;
                $stats['totals']['revenue'] += $tenantStats['revenue']['monthly'] ?? 0;
                
            } catch (\Exception $e) {
                Log::warning("Failed to get stats for tenant {$tenant->id}", [
                    'tenant' => $tenant->id,
                    'error' => $e->getMessage()
                ]);
            }
        }
        
        return $stats;
    }
    
    /**
     * Get tenant-specific dashboard statistics
     */
    private static function getTenantDashboardStats(string $tenantId): array
    {
        $today = now()->startOfDay();
        $thisMonth = now()->startOfMonth();
        
        // Customer stats
        $customers = DB::connection('pgsql')
            ->table('customers')
            ->where('tenant_id', $tenantId);
        
        $customerStats = [
            'total' => $customers->count(),
            'active' => (clone $customers)->where('is_active', true)->count(),
            'new_today' => (clone $customers)->whereDate('created_at', $today)->count(),
            'new_this_month' => (clone $customers)->where('created_at', '>=', $thisMonth)->count(),
        ];
        
        // Payment stats
        $payments = DB::connection('pgsql')
            ->table('payments')
            ->where('tenant_id', $tenantId);
        
        $paymentStats = [
            'total' => $payments->count(),
            'completed' => (clone $payments)->where('status', 'completed')->count(),
            'pending' => (clone $payments)->where('status', 'pending')->count(),
            'failed' => (clone $payments)->where('status', 'failed')->count(),
            'today' => (clone $payments)->whereDate('created_at', $today)->count(),
        ];
        
        // Revenue stats
        $revenueToday = DB::connection('pgsql')
            ->table('payments')
            ->where('tenant_id', $tenantId)
            ->where('status', 'completed')
            ->whereDate('paid_at', $today)
            ->sum('amount');
        
        $revenueMonth = DB::connection('pgsql')
            ->table('payments')
            ->where('tenant_id', $tenantId)
            ->where('status', 'completed')
            ->where('paid_at', '>=', $thisMonth)
            ->sum('amount');
        
        // Voucher stats
        $vouchers = DB::connection('pgsql')
            ->table('vouchers')
            ->where('tenant_id', $tenantId);
        
        $voucherStats = [
            'total' => $vouchers->count(),
            'active' => (clone $vouchers)->where('status', 'active')->count(),
            'expired' => (clone $vouchers)->where('status', 'expired')->count(),
            'used' => (clone $vouchers)->where('status', 'used')->count(),
            'generated_today' => (clone $vouchers)->whereDate('created_at', $today)->count(),
        ];
        
        // MikroTik device stats (if table exists)
        $deviceStats = [
            'total' => 0,
            'online' => 0,
            'offline' => 0,
        ];
        
        try {
            if (DB::connection('pgsql')->getSchemaBuilder()->hasTable('mikrotik_devices')) {
                $devices = DB::connection('pgsql')
                    ->table('mikrotik_devices')
                    ->where('tenant_id', $tenantId);
                
                $deviceStats = [
                    'total' => $devices->count(),
                    'online' => (clone $devices)->where('status', 'online')->count(),
                    'offline' => (clone $devices)->where('status', 'offline')->count(),
                ];
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
                'today' => (float) $revenueToday,
                'monthly' => (float) $revenueMonth,
            ]
        ];
    }
}