<?php

use App\Http\Controllers\Api\Universal\PaymentCallbackController;
use App\Http\Controllers\Api\PaymentGatewayController;
use App\Http\Controllers\Api\ErrorController;
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

    // Universal payment callback (handles both central and tenant)
    Route::post('/payments/callback/collectug', function () {
        // This will be handled by a universal callback controller
        return app(PaymentCallbackController::class)->handle();
    })->name('api.payments.callback');

    // Error logging endpoints (available without authentication for frontend error reporting)
    Route::post('/errors', [ErrorController::class, 'logError'])->name('api.errors.log');
});

// Authenticated error management routes
Route::middleware(['auth:sanctum'])->withoutMiddleware(['throttle:api'])->prefix('v1')->group(function () {
    Route::get('/errors/stats', [ErrorController::class, 'getErrorStats'])->name('api.errors.stats');
    Route::get('/errors/{errorId}', [ErrorController::class, 'getErrorDetails'])->name('api.errors.details');
    Route::put('/errors/{errorId}/status', [ErrorController::class, 'updateErrorStatus'])->name('api.errors.update-status');
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
        Route::get('/', [\App\Http\Controllers\Api\PaymentController::class, 'index']);
        Route::get('/statistics', [\App\Http\Controllers\Api\PaymentController::class, 'statistics']);
        Route::get('/export', [\App\Http\Controllers\Api\PaymentController::class, 'export']);
        Route::put('/{payment}', [\App\Http\Controllers\Api\PaymentController::class, 'update']);
        Route::post('/reconciliation', [\App\Http\Controllers\Api\PaymentController::class, 'reconciliation']);
        Route::get('/reconciliation/export', [\App\Http\Controllers\Api\PaymentController::class, 'exportReconciliation']);
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
