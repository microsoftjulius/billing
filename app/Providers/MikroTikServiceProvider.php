<?php

namespace App\Providers;

use App\Services\MikroTikApiService;
use Illuminate\Support\ServiceProvider;

class MikroTikServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->singleton(MikroTikApiService::class, function ($app) {
            return new MikroTikApiService();
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}