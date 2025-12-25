<?php

namespace App\Console\Commands;

use App\Services\SmsService;
use Illuminate\Console\Command;

class CheckSmsBalance extends Command
{
    protected $signature = 'sms:check-balance {--alert-threshold=1000 : Alert when balance is below this amount}';
    protected $description = 'Check UGSMS account balance and send alerts if low';

    public function handle(SmsService $smsService): int
    {
        $balance = $smsService->checkBalance();
        $threshold = (float) $this->option('alert-threshold');

        $this->info("Current SMS balance: UGX " . number_format($balance, 2));

        if ($balance < $threshold) {
            $this->warn("Balance is below threshold of UGX " . number_format($threshold));

            // Send alert
            $alertSent = $smsService->sendLowBalanceAlert($balance);

            if ($alertSent) {
                $this->info("Low balance alert sent to admin");
            } else {
                $this->error("Failed to send low balance alert");
            }
        }

        // Log balance to monitoring system
        \Log::channel('monitoring')->info('SMS balance check', [
            'balance' => $balance,
            'threshold' => $threshold,
            'is_low' => $balance < $threshold
        ]);

        return 0;
    }
}
