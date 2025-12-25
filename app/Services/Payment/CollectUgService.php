<?php

namespace App\Services\Payment;

use App\Contracts\Payment\PaymentGatewayInterface;
use App\DTOs\Payment\PaymentRequestDTO;
use App\DTOs\Payment\PaymentResponseDTO;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class CollectUgService implements PaymentGatewayInterface
{
    private string $apiKey;
    private string $baseUrl;
    private string $callbackUrl;

    public function __construct(array $config)
    {
        $this->apiKey = $config['api_key'];
        $this->baseUrl = rtrim($config['base_url'], '/');
        $this->callbackUrl = $config['callback_url'] ?? route('payment.callback.collectug');

        Log::channel('payment')->info('CollectUg Service initialized', [
            'base_url' => $this->baseUrl
        ]);
    }

    public function initializePayment(PaymentRequestDTO $paymentRequest): PaymentResponseDTO
    {
        try {
            $transactionId = Str::uuid()->toString();
            $merchantRef = 'BILL-' . now()->format('Ymd') . '-' . Str::random(6);

            $payload = [
                'amount' => (int) $paymentRequest->amount,
                'phoneNumber' => $this->formatPhoneNumber($paymentRequest->customerPhone),
                'merchant_reference' => $merchantRef,
                'callback_url' => $this->callbackUrl,
                'metadata' => array_merge($paymentRequest->metadata, [
                    'customer_email' => $paymentRequest->customerEmail,
                    'description' => $paymentRequest->description,
                    'internal_ref' => $transactionId
                ])
            ];

            Log::channel('payment')->debug('CollectUg payment request', [
                'payload' => $payload,
                'transaction_id' => $transactionId
            ]);

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ])
                ->timeout(30)
                ->retry(3, 1000, function ($exception) {
                    return $exception instanceof \Illuminate\Http\Client\ConnectionException;
                })
                ->post($this->baseUrl . '/api/v1/payments/collect', $payload);

            $responseData = $response->json();

            if ($response->failed()) {
                throw new \Exception($responseData['message'] ?? 'Payment initialization failed');
            }

            Log::channel('payment')->info('CollectUg payment initiated', [
                'transaction_id' => $transactionId,
                'collectug_ref' => $responseData['transaction']['transaction_id'] ?? null,
                'status' => $responseData['transaction']['status'] ?? null
            ]);

            return new PaymentResponseDTO(
                success: true,
                transactionId: $transactionId,
                reference: $responseData['transaction']['transaction_id'] ?? null,
                message: $responseData['message'] ?? 'Payment initiated successfully',
                providerResponse: $responseData,
                redirectUrl: null,
                requiresMobileConfirmation: true
            );

        } catch (\Exception $e) {
            Log::channel('payment')->error('CollectUg payment initialization failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return new PaymentResponseDTO(
                success: false,
                transactionId: $transactionId ?? Str::uuid()->toString(),
                reference: null,
                message: 'Payment failed: ' . $e->getMessage(),
                providerResponse: ['error' => $e->getMessage()],
                redirectUrl: null,
                requiresMobileConfirmation: false
            );
        }
    }

    public function verifyPayment(string $transactionId): PaymentResponseDTO
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Accept' => 'application/json',
            ])
                ->timeout(15)
                ->get($this->baseUrl . '/api/v1/payments/verify/' . $transactionId);

            $responseData = $response->json();

            if ($response->failed()) {
                return new PaymentResponseDTO(
                    success: false,
                    transactionId: $transactionId,
                    message: 'Verification failed: ' . ($responseData['message'] ?? 'Unknown error')
                );
            }

            $isSuccessful = $responseData['transaction']['status'] === 'completed';

            return new PaymentResponseDTO(
                success: $isSuccessful,
                transactionId: $transactionId,
                reference: $responseData['transaction']['transaction_id'] ?? null,
                message: $responseData['message'] ?? 'Verification completed',
                providerResponse: $responseData,
                amount: $responseData['transaction']['amount'] ?? null,
                paidAt: $responseData['transaction']['paid_at'] ?? null
            );

        } catch (\Exception $e) {
            Log::channel('payment')->error('CollectUg payment verification failed', [
                'transaction_id' => $transactionId,
                'error' => $e->getMessage()
            ]);

            return new PaymentResponseDTO(
                success: false,
                transactionId: $transactionId,
                message: 'Verification failed: ' . $e->getMessage()
            );
        }
    }

    public function refundPayment(string $transactionId, float $amount): bool
    {
        try {
            $payload = [
                'transaction_id' => $transactionId,
                'amount' => (int) $amount,
                'reason' => 'Customer refund'
            ];

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ])
                ->post($this->baseUrl . '/api/v1/payments/refund', $payload);

            $responseData = $response->json();

            if ($response->failed()) {
                throw new \Exception($responseData['message'] ?? 'Refund failed');
            }

            Log::channel('payment')->info('CollectUg refund processed', [
                'transaction_id' => $transactionId,
                'amount' => $amount,
                'refund_id' => $responseData['refund_id'] ?? null
            ]);

            return true;

        } catch (\Exception $e) {
            Log::channel('payment')->error('CollectUg refund failed', [
                'transaction_id' => $transactionId,
                'error' => $e->getMessage()
            ]);

            return false;
        }
    }

    public function getSupportedCurrencies(): array
    {
        return ['UGX'];
    }

    public function getBalance(): array
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Accept' => 'application/json',
            ])
                ->get($this->baseUrl . '/api/v1/account/balance');

            $responseData = $response->json();

            return [
                'available_balance' => $responseData['available_balance'] ?? 0,
                'currency' => 'UGX',
                'account_status' => $responseData['status'] ?? 'unknown'
            ];

        } catch (\Exception $e) {
            Log::channel('payment')->error('Failed to fetch CollectUg balance', [
                'error' => $e->getMessage()
            ]);

            return [
                'available_balance' => 0,
                'currency' => 'UGX',
                'account_status' => 'unavailable'
            ];
        }
    }

    private function formatPhoneNumber(string $phone): string
    {
        // Remove any non-digit characters
        $phone = preg_replace('/[^0-9]/', '', $phone);

        // Ensure it starts with 256 (Uganda country code)
        if (strlen($phone) === 9 && str_starts_with($phone, '7')) {
            return '256' . $phone;
        }

        if (strlen($phone) === 10 && str_starts_with($phone, '0')) {
            return '256' . substr($phone, 1);
        }

        return $phone;
    }

    private function cachePaymentRequest(array $payload, string $transactionId): void
    {
        Cache::put('collectug.request.' . $transactionId, $payload, now()->addHours(2));
    }

    private function getCachedPaymentRequest(string $transactionId): ?array
    {
        return Cache::get('collectug.request.' . $transactionId);
    }

}
