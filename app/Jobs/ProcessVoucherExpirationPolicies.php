<?php

namespace App\Jobs;

use App\Models\Voucher;
use App\Services\VoucherService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessVoucherExpirationPolicies implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private array $policies;

    /**
     * Create a new job instance.
     */
    public function __construct(array $policies = [])
    {
        $this->policies = $policies;
    }

    /**
     * Execute the job.
     */
    public function handle(VoucherService $voucherService): void
    {
        try {
            Log::channel('voucher')->info('Processing voucher expiration policies', [
                'policies' => $this->policies,
                'job_id' => $this->job->getJobId()
            ]);

            // Mark expired vouchers
            $expiredCount = $this->markExpiredVouchers();

            // Run cleanup with policies
            $cleanupResult = $voucherService->cleanupExpiredVouchers($this->policies);

            Log::channel('voucher')->info('Voucher expiration policies processed successfully', [
                'expired_marked' => $expiredCount,
                'cleanup_result' => $cleanupResult,
                'job_id' => $this->job->getJobId()
            ]);

        } catch (\Exception $e) {
            Log::channel('voucher')->error('Failed to process voucher expiration policies', [
                'policies' => $this->policies,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'job_id' => $this->job->getJobId()
            ]);

            throw $e;
        }
    }

    /**
     * Mark vouchers as expired if they have passed their expiry time
     */
    private function markExpiredVouchers(): int
    {
        $expiredVouchers = Voucher::where('status', 'active')
            ->where('expires_at', '<', now())
            ->get();

        $count = 0;
        foreach ($expiredVouchers as $voucher) {
            try {
                $voucher->markAsExpired();
                $count++;

                Log::channel('voucher')->debug('Voucher marked as expired', [
                    'voucher_id' => $voucher->id,
                    'code' => $voucher->code,
                    'expired_at' => $voucher->expires_at
                ]);

            } catch (\Exception $e) {
                Log::channel('voucher')->error('Failed to mark voucher as expired', [
                    'voucher_id' => $voucher->id,
                    'code' => $voucher->code,
                    'error' => $e->getMessage()
                ]);
            }
        }

        return $count;
    }

    /**
     * Get the tags that should be assigned to the job.
     */
    public function tags(): array
    {
        return ['vouchers', 'expiration', 'cleanup'];
    }
}