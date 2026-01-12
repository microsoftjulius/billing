<?php

namespace App\Console\Commands;

use App\Services\VoucherService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CleanupExpiredVouchers extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'vouchers:cleanup 
                            {--auto-disable-after-days=30 : Days after expiry to auto-disable vouchers}
                            {--delete-after-days=90 : Days after disable to delete vouchers}
                            {--dry-run : Show what would be cleaned up without actually doing it}';

    /**
     * The console command description.
     */
    protected $description = 'Cleanup expired vouchers based on configured policies';

    private VoucherService $voucherService;

    public function __construct(VoucherService $voucherService)
    {
        parent::__construct();
        $this->voucherService = $voucherService;
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Starting voucher cleanup process...');

        $policies = [
            'auto_disable_after_days' => (int) $this->option('auto-disable-after-days'),
            'delete_after_days' => (int) $this->option('delete-after-days'),
            'notify_before_cleanup' => true
        ];

        $isDryRun = $this->option('dry-run');

        if ($isDryRun) {
            $this->warn('DRY RUN MODE - No actual changes will be made');
            
            // Show what would be cleaned up
            $expiredVouchers = \App\Models\Voucher::where('status', 'expired')
                ->where('expires_at', '<', now()->subDays($policies['auto_disable_after_days']))
                ->count();
                
            $oldVouchers = \App\Models\Voucher::where('status', 'disabled')
                ->where('updated_at', '<', now()->subDays($policies['delete_after_days']))
                ->count();

            $this->table(
                ['Action', 'Count', 'Criteria'],
                [
                    ['Disable', $expiredVouchers, "Expired > {$policies['auto_disable_after_days']} days ago"],
                    ['Delete', $oldVouchers, "Disabled > {$policies['delete_after_days']} days ago"]
                ]
            );

            return self::SUCCESS;
        }

        try {
            $result = $this->voucherService->cleanupExpiredVouchers($policies);

            // Display results
            $this->info("Cleanup completed successfully!");
            $this->table(
                ['Action', 'Count'],
                [
                    ['Vouchers Disabled', $result['disabled']],
                    ['Vouchers Deleted', $result['deleted']],
                    ['Notifications Sent', $result['notified']],
                    ['Errors', count($result['errors'])]
                ]
            );

            if (!empty($result['errors'])) {
                $this->error('Errors encountered during cleanup:');
                foreach ($result['errors'] as $error) {
                    $this->line("  - {$error}");
                }
            }

            Log::channel('voucher')->info('Voucher cleanup command completed', [
                'policies' => $policies,
                'result' => $result,
                'executed_by' => 'console_command'
            ]);

            return self::SUCCESS;

        } catch (\Exception $e) {
            $this->error("Cleanup failed: {$e->getMessage()}");
            
            Log::channel('voucher')->error('Voucher cleanup command failed', [
                'policies' => $policies,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return self::FAILURE;
        }
    }
}