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
Route::middleware(['auth:sanctum'])->withoutMiddleware(['throttle:api'])->group(function () {
    Route::apiResource('payment-gateways', PaymentGatewayController::class);
    Route::patch('/payment-gateways/{paymentGateway}/toggle', [PaymentGatewayController::class, 'toggle']);
    Route::post('/payment-gateways/{paymentGateway}/test', [PaymentGatewayController::class, 'test']);
    Route::post('/payment-gateways/test', [PaymentGatewayController::class, 'test']);
    Route::get('/payment-gateways/{paymentGateway}/statistics', [PaymentGatewayController::class, 'statistics']);
});
