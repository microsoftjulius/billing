<?php

namespace App\Services\Payment;

use App\Contracts\Payment\PaymentGatewayInterface;
use App\DTOs\Payment\PaymentRequestDTO;
use App\DTOs\Payment\PaymentResponseDTO;
use App\Models\PaymentGateway;
use App\Services\Payment\CollectUgService;
use Illuminate\Support\Facades\Log;

class PaymentGatewayManager
{
    private array $gateways = [];

    public function __construct()
    {
        $this->loadActiveGateways();
    }

    /**
     * Process payment through the best available gateway
     */
    public function processPayment(PaymentRequestDTO $request, ?string $preferredProvider = null): PaymentResponseDTO
    {
        $gateway = $this->selectGateway($request, $preferredProvider);

        if (!$gateway) {
            return new PaymentResponseDTO(
                success: false,
                transactionId: 'NO-GATEWAY-' . uniqid(),
                message: 'No suitable payment gateway available'
            );
        }

        try {
            Log::info('Processing payment through gateway', [
                'provider' => $gateway['provider'],
                'amount' => $request->amount,
                'currency' => $request->currency
            ]);

            $service = $this->createGatewayService($gateway);
            return $service->initializePayment($request);

        } catch (\Exception $e) {
            Log::error('Payment processing failed', [
                'provider' => $gateway['provider'],
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return new PaymentResponseDTO(
                success: false,
                transactionId: 'ERROR-' . uniqid(),
                message: 'Payment processing failed: ' . $e->getMessage()
            );
        }
    }

    /**
     * Verify payment through specific gateway
     */
    public function verifyPayment(string $transactionId, string $provider): PaymentResponseDTO
    {
        $gateway = $this->getGatewayByProvider($provider);

        if (!$gateway) {
            return new PaymentResponseDTO(
                success: false,
                transactionId: $transactionId,
                message: 'Gateway not found or inactive'
            );
        }

        try {
            $service = $this->createGatewayService($gateway);
            return $service->verifyPayment($transactionId);

        } catch (\Exception $e) {
            Log::error('Payment verification failed', [
                'provider' => $provider,
                'transaction_id' => $transactionId,
                'error' => $e->getMessage()
            ]);

            return new PaymentResponseDTO(
                success: false,
                transactionId: $transactionId,
                message: 'Payment verification failed: ' . $e->getMessage()
            );
        }
    }

    /**
     * Get all active gateways
     */
    public function getActiveGateways(): array
    {
        return $this->gateways;
    }

    /**
     * Get gateway by provider
     */
    public function getGatewayByProvider(string $provider): ?array
    {
        return $this->gateways[$provider] ?? null;
    }

    /**
     * Check if gateway supports currency
     */
    public function supportsCurrency(string $provider, string $currency): bool
    {
        $gateway = $this->getGatewayByProvider($provider);
        return $gateway && in_array($currency, $gateway['supported_currencies']);
    }

    /**
     * Check if gateway supports payment method
     */
    public function supportsPaymentMethod(string $provider, string $method): bool
    {
        $gateway = $this->getGatewayByProvider($provider);
        return $gateway && in_array($method, $gateway['supported_methods']);
    }

    /**
     * Load active gateways from database
     */
    private function loadActiveGateways(): void
    {
        $gateways = PaymentGateway::active()->get();

        foreach ($gateways as $gateway) {
            $this->gateways[$gateway->provider] = [
                'id' => $gateway->id,
                'name' => $gateway->name,
                'provider' => $gateway->provider,
                'configuration' => $this->decryptConfiguration($gateway->configuration, $gateway->provider),
                'webhook_url' => $gateway->webhook_url,
                'supported_currencies' => $gateway->supported_currencies,
                'supported_methods' => $gateway->supported_methods,
            ];
        }

        Log::info('Loaded payment gateways', [
            'count' => count($this->gateways),
            'providers' => array_keys($this->gateways)
        ]);
    }

    /**
     * Select the best gateway for a payment request
     */
    private function selectGateway(PaymentRequestDTO $request, ?string $preferredProvider = null): ?array
    {
        // If preferred provider is specified and available, use it
        if ($preferredProvider && isset($this->gateways[$preferredProvider])) {
            $gateway = $this->gateways[$preferredProvider];
            if ($this->isGatewaySuitable($gateway, $request)) {
                return $gateway;
            }
        }

        // Find the first suitable gateway
        foreach ($this->gateways as $gateway) {
            if ($this->isGatewaySuitable($gateway, $request)) {
                return $gateway;
            }
        }

        return null;
    }

    /**
     * Check if gateway is suitable for the request
     */
    private function isGatewaySuitable(array $gateway, PaymentRequestDTO $request): bool
    {
        // Check currency support
        if (!in_array($request->currency, $gateway['supported_currencies'])) {
            return false;
        }

        // Check if gateway is properly configured
        if (!$this->isGatewayConfigured($gateway)) {
            return false;
        }

        return true;
    }

    /**
     * Check if gateway is properly configured
     */
    private function isGatewayConfigured(array $gateway): bool
    {
        $requiredFields = $this->getRequiredConfigurationFields($gateway['provider']);

        foreach ($requiredFields as $field) {
            if (empty($gateway['configuration'][$field])) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get required configuration fields for provider
     */
    private function getRequiredConfigurationFields(string $provider): array
    {
        return match ($provider) {
            'collectug' => ['api_key', 'base_url'],
            'stripe' => ['secret_key'],
            'paypal' => ['client_id', 'client_secret', 'environment'],
            default => []
        };
    }

    /**
     * Create gateway service instance
     */
    private function createGatewayService(array $gateway): PaymentGatewayInterface
    {
        return match ($gateway['provider']) {
            'collectug' => new CollectUgService($gateway['configuration']),
            'stripe' => throw new \Exception('Stripe gateway not implemented'),
            'paypal' => throw new \Exception('PayPal gateway not implemented'),
            default => throw new \Exception('Unsupported gateway provider: ' . $gateway['provider'])
        };
    }

    /**
     * Decrypt gateway configuration
     */
    private function decryptConfiguration(array $configuration, string $provider): array
    {
        $sensitiveFields = $this->getSensitiveFields($provider);

        foreach ($sensitiveFields as $field) {
            if (isset($configuration[$field])) {
                try {
                    $configuration[$field] = decrypt($configuration[$field]);
                } catch (\Exception $e) {
                    Log::warning('Failed to decrypt configuration field', [
                        'field' => $field,
                        'provider' => $provider
                    ]);
                }
            }
        }

        return $configuration;
    }

    /**
     * Get sensitive fields for provider
     */
    private function getSensitiveFields(string $provider): array
    {
        return match ($provider) {
            'collectug' => ['api_key'],
            'stripe' => ['secret_key', 'webhook_secret'],
            'paypal' => ['client_secret'],
            default => []
        };
    }

    /**
     * Refresh gateways from database
     */
    public function refresh(): void
    {
        $this->gateways = [];
        $this->loadActiveGateways();
    }
}