<?php

namespace App\Services\Payment;

use App\Contracts\Payment\PaymentGatewayInterface;
use App\DTOs\Payment\PaymentRequestDTO;
use App\DTOs\Payment\PaymentResponseDTO;
use App\Repositories\PaymentRepository;
use App\Jobs\ProcessSuccessfulPayment;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

abstract class PaymentService implements PaymentGatewayInterface
{
    protected PaymentRepository $paymentRepository;
    protected array $config;

    public function __construct(array $config)
    {
        $this->config = $config;
        $this->paymentRepository = app(PaymentRepository::class);
    }

    abstract protected function makeApiRequest(array $payload): array;

    public function verifyPayment(string $transactionId): PaymentResponseDTO
    {
        return Cache::remember("payment.verify.{$transactionId}", 300, function () use ($transactionId) {
            return $this->performVerification($transactionId);
        });
    }

    protected function processSuccessfulPayment(PaymentResponseDTO $response): void
    {
        ProcessSuccessfulPayment::dispatch($response)->onQueue('payments');
    }

    protected function logPayment(string $level, string $message, array $context = []): void
    {
        Log::channel('payment')->$level($message, $context);
    }
}
