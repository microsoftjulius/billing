<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Event;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;
use Stancl\Tenancy\Events\TenantCreated;
use Stancl\Tenancy\Events\TenantDeleted;

class TenancyServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Only bindings should go here
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        $this->registerTenancyEvents();
        $this->configureRoutes();
        $this->configureTenancy();
    }

    /**
     * Configure the tenant and central routes.
     */
    protected function configureRoutes(): void
    {
        // Skip route configuration if running in console
        if ($this->app->runningInConsole()) {
            return;
        }

        // Determine central domain
        $centralDomain = config(
            'app.central_domain',
            parse_url(config('app.url'), PHP_URL_HOST)
        );

        /**
         * -------------------------------------------------
         * Central Domain Routes
         * -------------------------------------------------
         */
        Route::domain($centralDomain)->group(function () {

            // Central web routes
            Route::middleware('web')
                ->group(base_path('routes/central/web.php'));

            // Central API routes
            Route::middleware('api')
                ->prefix('api')
                ->group(base_path('routes/central/api.php'));

            // Universal routes (central only)
            Route::middleware('universal')->group(function () {
                Route::get('/health', function () {
                    return response()->json([
                        'status' => 'healthy',
                        'context' => 'central',
                        'timestamp' => now()->toISOString(),
                    ]);
                });
            });
        });

        /**
         * -------------------------------------------------
         * Tenant Routes (Domain-based)
         * -------------------------------------------------
         */
        Route::middleware([
            'web',
            PreventAccessFromCentralDomains::class,
            InitializeTenancyByDomain::class,
        ])->group(base_path('routes/tenant/web.php'));

        Route::middleware([
            'api',
            PreventAccessFromCentralDomains::class,
            InitializeTenancyByDomain::class,
        ])->prefix('api')
            ->group(base_path('routes/tenant/api.php'));
    }

    /**
     * Configure tenancy settings.
     */
    protected function configureTenancy(): void
    {
        $this->configureDatabase();
        $this->registerConsoleCommands();
    }

    /**
     * Configure database connections.
     */
    protected function configureDatabase(): void
    {
        // Central DB connection
        config([
            'database.default' => 'pgsql_central',
        ]);

        // Tenant DB naming
        config([
            'tenancy.database.prefix' => env('TENANCY_DB_PREFIX', 'tenant_'),
            'tenancy.database.suffix' => env('TENANCY_DB_SUFFIX', ''),
        ]);
    }

    /**
     * Register tenancy lifecycle events.
     */
    protected function registerTenancyEvents(): void
    {
        Event::listen(TenantCreated::class, function (TenantCreated $event) {

            // Create tenant database/schema
            $event->tenant->createDatabase();

            // Run tenant migrations
            $event->tenant->run(function () {
                \Artisan::call('migrate', [
                    '--path' => 'database/migrations/tenant',
                    '--force' => true,
                ]);
            });

            // Seed tenant database
            $event->tenant->run(function () {
                \Artisan::call('db:seed', [
                    '--class' => 'TenantDatabaseSeeder',
                    '--force' => true,
                ]);
            });

            \Log::channel('tenancy')->info('Tenant created', [
                'tenant_id' => $event->tenant->id,
                'name' => $event->tenant->name,
                'domain' => optional($event->tenant->domains->first())->domain,
            ]);
        });

        Event::listen(TenantDeleted::class, function (TenantDeleted $event) {

            // Drop tenant database/schema
            $event->tenant->deleteDatabase();

            \Log::channel('tenancy')->info('Tenant deleted', [
                'tenant_id' => $event->tenant->id,
                'name' => $event->tenant->name,
            ]);
        });
    }

    /**
     * Register tenancy console commands.
     */
    protected function registerConsoleCommands(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }
    }
}
