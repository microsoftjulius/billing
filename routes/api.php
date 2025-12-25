<?php

use App\Http\Controllers\Api\Universal\PaymentCallbackController;
use Illuminate\Support\Facades\Route;

// Universal API routes (work in both central and tenant contexts)
Route::prefix('v1')->group(function () {
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
});
