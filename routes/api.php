<?php

use App\Http\Controllers\Api\Universal\PaymentCallbackController;
use App\Http\Controllers\Api\PaymentGatewayController;
use App\Http\Controllers\Api\ErrorController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\VoucherController;
use App\Http\Controllers\Api\PaymentController;
use Illuminate\Support\Facades\Route;

// Universal API routes (work in both central and tenant contexts)
Route::prefix('v1')->withoutMiddleware(['throttle:api'])->group(function () {
    // Universal health check
    Route::get('/health', function () {
        $tenant = tenant();

        return response()->json([
            'status' => 'healthy',
            'timestamp' => now()->toISOString(),
            'service' => 'Billing System API',
            'version' => '1.0.0',
            'context' => $tenant ? 'tenant' : 'central',
            'tenant_id' => $tenant?->id,
            'tenant_name' => $tenant?->name,
        ]);
    })->name('api.health');

    // Test voucher statistics endpoint (bypasses tenancy)
    Route::get('/vouchers/statistics', function() {
        // Mock statistics data for frontend testing
        $stats = [
            'active_vouchers' => 150,
            'expired_vouchers' => 45,
            'total_revenue' => 2500000,
            'today_vouchers' => 12,
            'total_vouchers' => 195,
            'unused_vouchers' => 30,
            'used_vouchers' => 120,
            'disabled_vouchers' => 25,
        ];

        return response()->json([
            'success' => true,
            'data' => $stats
        ]);
    })->middleware('auth:sanctum')->name('api.vouchers.statistics.test');

    // Universal payment callback (handles both central and tenant)
    Route::post('/payments/callback/collectug', function () {
        // This will be handled by a universal callback controller
        return app(PaymentCallbackController::class)->handle();
    })->name('api.payments.callback.collectug');

    // Error logging endpoints (available without authentication for frontend error reporting)
    Route::post('/errors', [ErrorController::class, 'logError'])->name('api.errors.log');

    // Authentication routes (public)
    Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:login')->name('api.auth.login');
    Route::post('/auth/register', [AuthController::class, 'register'])->middleware('throttle:register')->name('api.auth.register');
    Route::post('/auth/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum')->name('api.auth.logout');
    Route::get('/auth/me', [AuthController::class, 'me'])->middleware('auth:sanctum')->name('api.auth.me');

    // Public payment routes (for payment initiation and verification)
    Route::post('/payments/initiate', [PaymentController::class, 'initiate'])->name('api.payments.initiate');
    Route::get('/payments/{transactionId}/verify', [PaymentController::class, 'verify'])->name('api.payments.verify');
    Route::get('/payments/{transactionId}', [PaymentController::class, 'show'])->name('api.payments.show');
    Route::post('/payments/callback', [PaymentController::class, 'callback'])->name('api.payments.callback');
    
    // Tenant-specific payment routes (with tenant parameter)
    Route::get('/tenants/{tenant}/payments/{transactionId}/verify', [PaymentController::class, 'verify'])->name('api.tenants.payments.verify');
    Route::get('/tenants/{tenant}/payments/{transactionId}', [PaymentController::class, 'show'])->name('api.tenants.payments.show');
});

// Authenticated error management routes
Route::middleware(['auth:sanctum'])->withoutMiddleware(['throttle:api'])->prefix('v1')->group(function () {
    Route::get('/errors/stats', [ErrorController::class, 'getErrorStats'])->name('api.errors.stats');
    Route::get('/errors/{errorId}', [ErrorController::class, 'getErrorDetails'])->name('api.errors.details');
    Route::put('/errors/{errorId}/status', [ErrorController::class, 'updateErrorStatus'])->name('api.errors.update-status');

    // Dashboard routes
    Route::get('/dashboard/stats', [DashboardController::class, 'stats'])->name('api.dashboard.stats');
    Route::get('/dashboard/recent-payments', [DashboardController::class, 'recentPayments'])->name('api.dashboard.recent-payments');
    Route::get('/dashboard/recent-vouchers', [DashboardController::class, 'recentVouchers'])->name('api.dashboard.recent-vouchers');

    // Customer management routes
    Route::apiResource('customers', CustomerController::class);
    Route::get('/customers/{customer}/payments', [CustomerController::class, 'getPayments'])->name('api.customers.payments');
    Route::get('/customers/{customer}/vouchers', [CustomerController::class, 'getVouchers'])->name('api.customers.vouchers');
    Route::post('/customers/{customer}/send-sms', [CustomerController::class, 'sendSms'])->name('api.customers.send-sms');
    Route::get('/customers/search', [CustomerController::class, 'search'])->name('api.customers.search');

    // Test routes without tenancy middleware
    Route::get('/test-voucher-stats', function() {
        return response()->json(['success' => true, 'message' => 'Test route works']);
    });
    
    // Voucher statistics route with custom middleware handling
    Route::get('/vouchers/statistics', function() {
        try {
            // Manually handle tenant context for testing
            $user = auth()->user();
            if (!$user) {
                return response()->json(['success' => false, 'message' => 'Unauthenticated'], 401);
            }
            
            // For now, return mock statistics to test frontend
            $stats = [
                'active_vouchers' => 150,
                'expired_vouchers' => 45,
                'total_revenue' => 2500000,
                'today_vouchers' => 12,
                'total_vouchers' => 195,
                'unused_vouchers' => 30,
                'used_vouchers' => 120,
                'disabled_vouchers' => 25,
            ];

            return response()->json([
                'success' => true,
                'data' => $stats
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get voucher statistics: ' . $e->getMessage()
            ], 500);
        }
    })->name('api.vouchers.statistics');
    Route::get('/vouchers/analytics', [VoucherController::class, 'analytics'])->name('api.vouchers.analytics');
    Route::get('/vouchers/export', [VoucherController::class, 'export'])->name('api.vouchers.export');
    Route::get('/vouchers/search', [VoucherController::class, 'search'])->name('api.vouchers.search');
    
    // Advanced voucher generation
    Route::post('/vouchers/generate-advanced', [VoucherController::class, 'generateAdvanced'])->name('api.vouchers.generate-advanced');
    Route::post('/vouchers/batch-generate', [VoucherController::class, 'batchGenerate'])->name('api.vouchers.batch-generate');
    
    // Resource routes (must come after specific routes) - exclude show to avoid conflicts
    Route::apiResource('vouchers', VoucherController::class)->except(['show']);
    
    // Manual show route with specific parameter name
    Route::get('/vouchers/{code}', [VoucherController::class, 'show'])->name('vouchers.show');
    
    // Voucher management operations
    Route::post('/vouchers/cleanup', [VoucherController::class, 'cleanup'])->name('api.vouchers.cleanup');
    
    // Individual voucher operations
    Route::get('/vouchers/{code}/usage', [VoucherController::class, 'usage'])->name('api.vouchers.usage');
    Route::post('/vouchers/{code}/disable', [VoucherController::class, 'disable'])->name('api.vouchers.disable');
    Route::post('/vouchers/{code}/renew', [VoucherController::class, 'renew'])->name('api.vouchers.renew');
    Route::post('/vouchers/{code}/sync', [VoucherController::class, 'sync'])->name('api.vouchers.sync');
    Route::post('/vouchers/{code}/resend-sms', [VoucherController::class, 'resendSms'])->name('api.vouchers.resend-sms');
    Route::post('/vouchers/{code}/transfer', [VoucherController::class, 'transfer'])->name('api.vouchers.transfer');
    Route::post('/vouchers/{code}/refund', [VoucherController::class, 'refund'])->name('api.vouchers.refund');
});

// Test routes for payment gateways (for testing purposes)
Route::middleware(['auth:sanctum'])->withoutMiddleware(['throttle:api'])->prefix('v1')->group(function () {
    Route::apiResource('payment-gateways', PaymentGatewayController::class);
    Route::patch('/payment-gateways/{paymentGateway}/toggle', [PaymentGatewayController::class, 'toggle']);
    Route::post('/payment-gateways/{paymentGateway}/test', [PaymentGatewayController::class, 'test']);
    Route::post('/payment-gateways/test', [PaymentGatewayController::class, 'testMultiple']);
    Route::get('/payment-gateways/{paymentGateway}/statistics', [PaymentGatewayController::class, 'statistics']);
    Route::get('/payment-gateways/analytics', [PaymentGatewayController::class, 'analytics']);
    
    // Payment analytics and management routes
    Route::prefix('payments')->group(function () {
        Route::get('/statistics', [\App\Http\Controllers\Api\PaymentController::class, 'statistics']);
        Route::get('/export', [\App\Http\Controllers\Api\PaymentController::class, 'export']);
        Route::get('/reconciliation/export', [\App\Http\Controllers\Api\PaymentController::class, 'exportReconciliation']);
        Route::post('/reconciliation', [\App\Http\Controllers\Api\PaymentController::class, 'reconciliation']);
        Route::get('/', [\App\Http\Controllers\Api\PaymentController::class, 'index']);
        Route::put('/{payment}', [\App\Http\Controllers\Api\PaymentController::class, 'update']);
        Route::post('/discrepancies/{discrepancyId}/resolve', [\App\Http\Controllers\Api\PaymentController::class, 'resolveDiscrepancy']);
        Route::post('/discrepancies/{discrepancyId}/dispute', [\App\Http\Controllers\Api\PaymentController::class, 'flagDispute']);
        Route::get('/{transactionId}', [\App\Http\Controllers\Api\PaymentController::class, 'show']);
        Route::post('/{transactionId}/verify', [\App\Http\Controllers\Api\PaymentController::class, 'verify']);
    });
    
    // Router Management routes for testing
    Route::prefix('router-management')->group(function () {
        Route::get('/', [\App\Http\Controllers\Api\RouterManagementController::class, 'index']);
        Route::post('/', [\App\Http\Controllers\Api\RouterManagementController::class, 'store']);
        Route::get('/{id}', [\App\Http\Controllers\Api\RouterManagementController::class, 'show']);
        Route::put('/{id}', [\App\Http\Controllers\Api\RouterManagementController::class, 'update']);
        Route::delete('/{id}', [\App\Http\Controllers\Api\RouterManagementController::class, 'destroy']);
        Route::post('/test-connection', [\App\Http\Controllers\Api\RouterManagementController::class, 'testConnection']);
    });

    // MikroTik Configuration routes
    Route::prefix('router')->group(function () {
        // Statistics and monitoring
        Route::get('/{deviceId}/statistics', [\App\Http\Controllers\Api\MikroTikConfigurationController::class, 'getStatistics']);
        Route::get('/{deviceId}/monitor', [\App\Http\Controllers\Api\MikroTikConfigurationController::class, 'monitorStatus']);
        Route::post('/{deviceId}/test-connectivity', [\App\Http\Controllers\Api\MikroTikConfigurationController::class, 'testConnectivity']);
        Route::delete('/{deviceId}/cache', [\App\Http\Controllers\Api\MikroTikConfigurationController::class, 'clearCache']);
        
        // Interface management
        Route::get('/{deviceId}/interfaces', [\App\Http\Controllers\Api\MikroTikConfigurationController::class, 'getInterfaces']);
        Route::put('/{deviceId}/interfaces/{interfaceId}', [\App\Http\Controllers\Api\MikroTikConfigurationController::class, 'updateInterface']);
        Route::put('/{deviceId}/interfaces/{interfaceId}/toggle', [\App\Http\Controllers\Api\MikroTikConfigurationController::class, 'toggleInterface']);
        
        // User management
        Route::get('/{deviceId}/users', [\App\Http\Controllers\Api\MikroTikConfigurationController::class, 'getUsers']);
        Route::post('/{deviceId}/users', [\App\Http\Controllers\Api\MikroTikConfigurationController::class, 'addUser']);
        Route::put('/{deviceId}/users/{userId}/toggle', [\App\Http\Controllers\Api\MikroTikConfigurationController::class, 'toggleUser']);
        Route::delete('/{deviceId}/users/{userId}', [\App\Http\Controllers\Api\MikroTikConfigurationController::class, 'deleteUser']);
        
        // System logs
        Route::get('/{deviceId}/logs', [\App\Http\Controllers\Api\MikroTikConfigurationController::class, 'getLogs']);
        
        // Backup and restore
        Route::get('/{deviceId}/backups', [\App\Http\Controllers\Api\MikroTikConfigurationController::class, 'getBackups']);
        Route::post('/{deviceId}/backup', [\App\Http\Controllers\Api\MikroTikConfigurationController::class, 'createBackup']);
        Route::get('/{deviceId}/backups/{backupId}/download', [\App\Http\Controllers\Api\MikroTikConfigurationController::class, 'downloadBackup']);
        Route::post('/{deviceId}/backups/{backupId}/restore', [\App\Http\Controllers\Api\MikroTikConfigurationController::class, 'restoreBackup']);
        Route::delete('/{deviceId}/backups/{backupId}', [\App\Http\Controllers\Api\MikroTikConfigurationController::class, 'deleteBackup']);
    });
});
