<?php

use App\Http\Controllers\Central\DashboardController;
use App\Http\Controllers\Central\ReportController;
use App\Http\Controllers\Central\TenantController;
use App\Http\Controllers\Central\UserController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Central\AuthController;

Route::prefix('v1')->group(function () {
    // Public endpoints
    Route::post('/auth/register', [AuthController::class, 'register'])->name('central.auth.register');
    Route::post('/auth/login', [AuthController::class, 'login'])->name('central.auth.login');

    // Health check
    Route::get('/health', function () {
        return response()->json([
            'status' => 'healthy',
            'timestamp' => now()->toISOString(),
            'service' => 'Billing System SaaS',
            'context' => 'central'
        ]);
    })->name('central.health');

    // Protected endpoints (Central admin only)
    Route::middleware(['auth:sanctum', 'central.admin'])->group(function () {
        // Auth
        Route::post('/auth/logout', [AuthController::class, 'logout'])->name('central.auth.logout');
        Route::get('/auth/me', [AuthController::class, 'me'])->name('central.auth.me');

        // Dashboard
        Route::get('/dashboard/stats', [DashboardController::class, 'stats'])->name('central.dashboard.stats');
        Route::get('/dashboard/recent-activities', [DashboardController::class, 'recentActivities'])->name('central.dashboard.activities');

        // Tenant management
        Route::apiResource('tenants', TenantController::class);
        Route::post('/tenants/{tenant}/suspend', [TenantController::class, 'suspend'])->name('central.tenants.suspend');
        Route::post('/tenants/{tenant}/activate', [TenantController::class, 'activate'])->name('central.tenants.activate');
        Route::post('/tenants/{tenant}/update-plan', [TenantController::class, 'updatePlan'])->name('central.tenants.update-plan');
        Route::get('/tenants/{tenant}/usage', [TenantController::class, 'usage'])->name('central.tenants.usage');
        Route::get('/tenants/{tenant}/analytics', [TenantController::class, 'analytics'])->name('central.tenants.analytics');

        // Users (Central admin users)
        Route::apiResource('users', UserController::class);
        Route::post('/users/{user}/suspend', [UserController::class, 'suspend'])->name('central.users.suspend');
        Route::post('/users/{user}/activate', [UserController::class, 'activate'])->name('central.users.activate');

        // Reports
        Route::get('/reports/tenants', [ReportController::class, 'tenants'])->name('central.reports.tenants');
        Route::get('/reports/revenue', [ReportController::class, 'revenue'])->name('central.reports.revenue');
        Route::get('/reports/usage', [ReportController::class, 'usage'])->name('central.reports.usage');

        // Settings
        Route::get('/settings', [DashboardController::class, 'settings'])->name('central.settings.get');
        Route::put('/settings', [DashboardController::class, 'updateSettings'])->name('central.settings.update');

        // Tenant search/availability
        Route::get('/tenants/search/{query}', [TenantController::class, 'search'])->name('central.tenants.search');
        Route::get('/tenants/check-availability/{field}/{value}', [TenantController::class, 'checkAvailability'])->name('central.tenants.check-availability');
    });
});
