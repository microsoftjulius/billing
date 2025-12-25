<?php

namespace App\Jobs;

use App\DTOs\Payment\PaymentResponseDTO;
use App\Models\Payment;
use App\Services\VoucherService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessSuccessfulPayment implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 120;
    public int $backoff = 60;

    public function __construct(
        private Payment $payment,
        private ?PaymentResponseDTO $paymentResponse = null
    ) {}

    public function handle(VoucherService $voucherService): void
    {
        try {
            Log::channel('payment')->info('Processing successful payment', [
                'payment_id' => $this->payment->id,
                'transaction_id' => $this->payment->transaction_id,
                'amount' => $this->payment->amount
            ]);

            // Generate voucher
            $voucher = $voucherService->generateVoucher($this->payment);

            Log::channel('payment')->info('Payment processed successfully', [
                'transaction_id' => $this->payment->transaction_id,
                'voucher_code' => $voucher->code,
                'voucher_id' => $voucher->id
            ]);

        } catch (\Exception $e) {
            Log::channel('payment')->error('Payment processing failed', [
                'error' => $e->getMessage(),
                'transaction_id' => $this->payment->transaction_id,
                'trace' => $e->getTraceAsString()
            ]);

            $this->fail($e);
        }
    }

    public function retryUntil()
    {
        return now()->addMinutes(10);
    }

    public function failed(\Throwable $exception): void
    {
        Log::channel('payment')->critical('Payment processing job failed after retries', [
            'payment_id' => $this->payment->id,
            'transaction_id' => $this->payment->transaction_id,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString()
        ]);

        // Optionally, send alert to admin
        // $this->sendFailureAlert($exception);
    }
}
