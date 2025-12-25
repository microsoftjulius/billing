<?php

use App\Http\Controllers\Api\SmsController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\VoucherController;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\RouterController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\SettingController;

Route::prefix('v1')->group(function () {
    // Public endpoints (no authentication required)
    Route::post('/auth/login', [AuthController::class, 'login'])->name('tenant.auth.login');
    Route::post('/payments/initiate', [PaymentController::class, 'initiate'])->name('tenant.payments.initiate');
    Route::get('/payments/verify/{transactionId}', [PaymentController::class, 'verify'])->name('tenant.payments.verify');
    Route::post('/payments/callback/collectug', [PaymentController::class, 'callback'])->name('tenant.payments.callback');

    // Health check
    Route::get('/health', function () {
        return response()->json([
            'status' => 'healthy',
            'timestamp' => now()->toISOString(),
            'service' => 'Billing System',
            'tenant' => tenant('id'),
            'tenant_name' => tenant('name')
        ]);
    })->name('tenant.health');

    // Protected endpoints (Tenant authentication required)
    Route::middleware(['auth:sanctum'])->group(function () {
        // Authentication
        Route::post('/auth/logout', [AuthController::class, 'logout'])->name('tenant.auth.logout');
        Route::get('/auth/me', [AuthController::class, 'me'])->name('tenant.auth.me');

        // Dashboard
        Route::get('/dashboard/stats', [DashboardController::class, 'stats'])->name('tenant.dashboard.stats');
        Route::get('/dashboard/recent-payments', [DashboardController::class, 'recentPayments'])->name('tenant.dashboard.recent-payments');
        Route::get('/dashboard/recent-vouchers', [DashboardController::class, 'recentVouchers'])->name('tenant.dashboard.recent-vouchers');

        // Payments
        Route::apiResource('payments', PaymentController::class);
        Route::get('/payments/{payment}/voucher', [PaymentController::class, 'voucher'])->name('tenant.payments.voucher');
        Route::get('/payments/{payment}/receipt', [PaymentController::class, 'receipt'])->name('tenant.payments.receipt');
        Route::get('/payments/statistics/overview', [PaymentController::class, 'statistics'])->name('tenant.payments.statistics');
        Route::get('/payments/export', [PaymentController::class, 'export'])->name('tenant.payments.export');

        // Vouchers
        Route::apiResource('vouchers', VoucherController::class);
        Route::post('/vouchers/batch-generate', [VoucherController::class, 'batchGenerate'])->name('tenant.vouchers.batch-generate');
        Route::post('/vouchers/{voucher}/disable', [VoucherController::class, 'disable'])->name('tenant.vouchers.disable');
        Route::post('/vouchers/{voucher}/extend', [VoucherController::class, 'extend'])->name('tenant.vouchers.extend');
        Route::get('/vouchers/{voucher}/usage', [VoucherController::class, 'usage'])->name('tenant.vouchers.usage');
        Route::get('/vouchers/statistics', [VoucherController::class, 'statistics'])->name('tenant.vouchers.statistics');
        Route::get('/vouchers/export', [VoucherController::class, 'export'])->name('tenant.vouchers.export');

        // Customers
        Route::apiResource('customers', CustomerController::class);
        Route::get('/customers/{customer}/payments', [CustomerController::class, 'payments'])->name('tenant.customers.payments');
        Route::get('/customers/{customer}/vouchers', [CustomerController::class, 'vouchers'])->name('tenant.customers.vouchers');
        Route::get('/customers/{customer}/activity', [CustomerController::class, 'activity'])->name('tenant.customers.activity');
        Route::get('/customers/statistics', [CustomerController::class, 'statistics'])->name('tenant.customers.statistics');
        Route::get('/customers/export', [CustomerController::class, 'export'])->name('tenant.customers.export');

        // Router/MikroTik
        Route::prefix('router')->group(function () {
            Route::get('/status', [RouterController::class, 'status'])->name('tenant.router.status');
            Route::get('/active-users', [RouterController::class, 'activeUsers'])->name('tenant.router.active-users');
            Route::get('/system-resources', [RouterController::class, 'systemResources'])->name('tenant.router.system-resources');
            Route::get('/interfaces', [RouterController::class, 'interfaces'])->name('tenant.router.interfaces');
            Route::get('/hotspot-profiles', [RouterController::class, 'hotspotProfiles'])->name('tenant.router.hotspot-profiles');
            Route::post('/sync-vouchers', [RouterController::class, 'syncVouchers'])->name('tenant.router.sync-vouchers');
            Route::post('/cleanup-expired', [RouterController::class, 'cleanupExpired'])->name('tenant.router.cleanup-expired');
        });

        // Reports
        Route::prefix('reports')->group(function () {
            Route::get('/payments', [ReportController::class, 'payments'])->name('tenant.reports.payments');
            Route::get('/vouchers', [ReportController::class, 'vouchers'])->name('tenant.reports.vouchers');
            Route::get('/customers', [ReportController::class, 'customers'])->name('tenant.reports.customers');
            Route::get('/revenue', [ReportController::class, 'revenue'])->name('tenant.reports.revenue');
            Route::get('/usage', [ReportController::class, 'usage'])->name('tenant.reports.usage');
        });

        // Users (Tenant users - staff/admins)
        Route::middleware(['tenant.admin'])->group(function () {
            Route::apiResource('users', UserController::class);
            Route::post('/users/{user}/suspend', [UserController::class, 'suspend'])->name('tenant.users.suspend');
            Route::post('/users/{user}/activate', [UserController::class, 'activate'])->name('tenant.users.activate');
            Route::post('/users/{user}/change-role', [UserController::class, 'changeRole'])->name('tenant.users.change-role');
        });

        // Settings
        Route::middleware(['tenant.admin'])->group(function () {
            Route::get('/settings', [SettingController::class, 'index'])->name('tenant.settings.get');
            Route::put('/settings', [SettingController::class, 'update'])->name('tenant.settings.update');
            Route::post('/settings/test-sms', [SettingController::class, 'testSms'])->name('tenant.settings.test-sms');
            Route::post('/settings/test-payment', [SettingController::class, 'testPayment'])->name('tenant.settings.test-payment');
            Route::post('/settings/test-router', [SettingController::class, 'testRouter'])->name('tenant.settings.test-router');
        });

        // SMS
        Route::get('/sms/balance', [SmsController::class, 'balance'])->name('tenant.sms.balance');
        Route::get('/sms/logs', [SmsController::class, 'logs'])->name('tenant.sms.logs');

        // Search endpoints
        Route::get('/search/customers/{query}', [CustomerController::class, 'search'])->name('tenant.search.customers');
        Route::get('/search/vouchers/{query}', [VoucherController::class, 'search'])->name('tenant.search.vouchers');
        Route::get('/search/payments/{query}', [PaymentController::class, 'search'])->name('tenant.search.payments');
    });
});
