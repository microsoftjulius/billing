<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Schedule MikroTik device monitoring every 30 seconds
Schedule::command('mikrotik:monitor')->everyThirtySeconds();

// Schedule MikroTik device monitoring every minute as backup
Schedule::command('mikrotik:monitor')->everyMinute()->when(function () {
    // Only run if the 30-second schedule is not available
    return !function_exists('everyThirtySeconds');
});

// Schedule voucher cleanup daily at 2 AM
Schedule::command('vouchers:cleanup')->dailyAt('02:00');

// Schedule voucher expiration processing every hour
Schedule::job(new \App\Jobs\ProcessVoucherExpirationPolicies())->hourly();