<?php

namespace App\Providers;

use App\Contracts\Payment\PaymentGatewayInterface;
use App\Contracts\Sms\SmsGatewayInterface;
use App\Services\Payment\CollectUgService;
use App\Services\Sms\UgSmsService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register services and bindings.
     */
    public function register(): void
    {
        // Register tenancy singleton
        $this->app->singleton(\Stancl\Tenancy\Tenancy::class, function ($app) {
            return new \Stancl\Tenancy\Tenancy($app);
        });

        // Payment Gateway Binding
        $this->app->bind(PaymentGatewayInterface::class, function ($app) {
            return new CollectUgService(config('services.collectug'));
        });

        // SMS Gateway Binding
        $this->app->bind(SmsGatewayInterface::class, function ($app) {
            return new UgSmsService(config('services.ugsms'));
        });

        // Register repositories
        $this->app->bind(
            \App\Contracts\Repositories\PaymentRepositoryInterface::class,
            \App\Repositories\PaymentRepository::class
        );

        // Bind tenant-specific services
        $this->registerTenantServices();
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Set default string length for PostgreSQL
        Schema::defaultStringLength(191);

        // Prevent lazy loading in production
        Model::preventLazyLoading(!app()->isProduction());

        // Register custom UUID macro
        $this->registerUuidMacro();

        // Configure tenancy commands for console
        $this->configureConsoleTenancy();
    }

    /**
     * Register a UUID macro that is ordered for indexing.
     */
    private function registerUuidMacro(): void
    {
        Str::macro('orderedUuid', function() {
            $uuid = (string) \Ramsey\Uuid\Uuid::uuid4();
            $timestamp = dechex(time());

            // Insert timestamp at beginning for better indexing
            return substr($timestamp, 0, 8) . '-' .
                substr($uuid, 9, 4) . '-' .
                substr($uuid, 14, 4) . '-' .
                substr($uuid, 19, 4) . '-' .
                substr($uuid, 24);
        });
    }

    /**
     * Bind tenant-specific services.
     */
    private function registerTenantServices(): void
    {
        // MikroTik service - tenant aware
        $this->app->bind(\App\Services\Router\MikrotikService::class, function ($app) {
            if (tenancy()->initialized) {
                $tenant = tenant();
                $config = array_merge(
                    config('services.mikrotik', []),
                    $tenant->metadata['mikrotik_config'] ?? []
                );
                return new \App\Services\Router\MikrotikService($config);
            }

            return new \App\Services\Router\MikrotikService();
        });

        // Voucher service - tenant aware
        $this->app->bind(\App\Services\VoucherService::class, function ($app) {
            return new \App\Services\VoucherService(
                $app->make(\App\Services\Router\MikrotikService::class),
                $app->make(\App\Services\SmsService::class)
            );
        });

        // Payment service - tenant aware
        $this->app->bind(\App\Services\PaymentService::class, function ($app) {
            return new \App\Services\PaymentService(
                $app->make(PaymentGatewayInterface::class)
            );
        });
    }

    /**
     * Configure tenancy commands for the console.
     */
    private function configureConsoleTenancy(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }
    }
}
