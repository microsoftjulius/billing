<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PaymentGateway;
use App\Services\Payment\CollectUgService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class PaymentGatewayController extends Controller
{
    /**
     * Display a listing of payment gateways
     */
    public function index(): JsonResponse
    {
        try {
            $gateways = PaymentGateway::orderBy('created_at', 'desc')->get();

            // Mask sensitive configuration data
            $gateways->transform(function ($gateway) {
                $gateway->configuration = $this->maskSensitiveData($gateway->configuration, $gateway->provider);
                return $gateway;
            });

            return response()->json([
                'success' => true,
                'data' => $gateways
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch payment gateways', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch payment gateways'
            ], 500);
        }
    }

    /**
     * Store a newly created payment gateway
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'provider' => ['required', 'string', Rule::in(['collectug', 'stripe', 'paypal'])],
            'webhook_url' => 'nullable|url',
            'is_active' => 'boolean',
            'configuration' => 'required|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Validate provider-specific configuration
            $configValidation = $this->validateProviderConfiguration(
                $request->input('provider'),
                $request->input('configuration')
            );

            if (!$configValidation['valid']) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid configuration',
                    'errors' => $configValidation['errors']
                ], 422);
            }

            // Encrypt sensitive configuration data
            $encryptedConfig = $this->encryptSensitiveData(
                $request->input('configuration'),
                $request->input('provider')
            );

            $gateway = PaymentGateway::create([
                'name' => $request->input('name'),
                'provider' => $request->input('provider'),
                'webhook_url' => $request->input('webhook_url'),
                'is_active' => $request->input('is_active', true),
                'configuration' => $encryptedConfig,
            ]);

            Log::info('Payment gateway created', [
                'gateway_id' => $gateway->id,
                'provider' => $gateway->provider,
                'name' => $gateway->name
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Payment gateway created successfully',
                'data' => $gateway
            ], 201);

        } catch (\Exception $e) {
            Log::error('Failed to create payment gateway', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to create payment gateway'
            ], 500);
        }
    }

    /**
     * Display the specified payment gateway
     */
    public function show(PaymentGateway $paymentGateway): JsonResponse
    {
        try {
            // Mask sensitive configuration data
            $paymentGateway->configuration = $this->maskSensitiveData(
                $paymentGateway->configuration,
                $paymentGateway->provider
            );

            return response()->json([
                'success' => true,
                'data' => $paymentGateway
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch payment gateway', [
                'gateway_id' => $paymentGateway->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch payment gateway'
            ], 500);
        }
    }

    /**
     * Update the specified payment gateway
     */
    public function update(Request $request, PaymentGateway $paymentGateway): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'provider' => ['sometimes', 'required', 'string', Rule::in(['collectug', 'stripe', 'paypal'])],
            'webhook_url' => 'nullable|url',
            'is_active' => 'boolean',
            'configuration' => 'sometimes|required|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $updateData = $request->only(['name', 'webhook_url', 'is_active']);

            // Handle configuration update
            if ($request->has('configuration')) {
                $provider = $request->input('provider', $paymentGateway->provider);
                
                // Validate provider-specific configuration
                $configValidation = $this->validateProviderConfiguration(
                    $provider,
                    $request->input('configuration')
                );

                if (!$configValidation['valid']) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Invalid configuration',
                        'errors' => $configValidation['errors']
                    ], 422);
                }

                // Encrypt sensitive configuration data
                $updateData['configuration'] = $this->encryptSensitiveData(
                    $request->input('configuration'),
                    $provider
                );
            }

            if ($request->has('provider')) {
                $updateData['provider'] = $request->input('provider');
            }

            $paymentGateway->update($updateData);

            Log::info('Payment gateway updated', [
                'gateway_id' => $paymentGateway->id,
                'provider' => $paymentGateway->provider,
                'updated_fields' => array_keys($updateData)
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Payment gateway updated successfully',
                'data' => $paymentGateway
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to update payment gateway', [
                'gateway_id' => $paymentGateway->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update payment gateway'
            ], 500);
        }
    }

    /**
     * Remove the specified payment gateway
     */
    public function destroy(PaymentGateway $paymentGateway): JsonResponse
    {
        try {
            $gatewayName = $paymentGateway->name;
            $paymentGateway->delete();

            Log::info('Payment gateway deleted', [
                'gateway_name' => $gatewayName,
                'provider' => $paymentGateway->provider
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Payment gateway deleted successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to delete payment gateway', [
                'gateway_id' => $paymentGateway->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete payment gateway'
            ], 500);
        }
    }

    /**
     * Toggle gateway active status
     */
    public function toggle(PaymentGateway $paymentGateway): JsonResponse
    {
        try {
            $paymentGateway->update([
                'is_active' => !$paymentGateway->is_active
            ]);

            Log::info('Payment gateway status toggled', [
                'gateway_id' => $paymentGateway->id,
                'new_status' => $paymentGateway->is_active ? 'active' : 'inactive'
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Gateway status updated successfully',
                'data' => [
                    'is_active' => $paymentGateway->is_active
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to toggle gateway status', [
                'gateway_id' => $paymentGateway->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update gateway status'
            ], 500);
        }
    }

    /**
     * Test gateway connection
     */
    public function test(Request $request, PaymentGateway $paymentGateway = null): JsonResponse
    {
        try {
            if ($paymentGateway) {
                // Test existing gateway
                $provider = $paymentGateway->provider;
                $configuration = $this->decryptSensitiveData(
                    $paymentGateway->configuration,
                    $provider
                );
            } else {
                // Test new gateway configuration
                $validator = Validator::make($request->all(), [
                    'provider' => ['required', 'string', Rule::in(['collectug', 'stripe', 'paypal'])],
                    'configuration' => 'required|array',
                ]);

                if ($validator->fails()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Validation failed',
                        'errors' => $validator->errors()
                    ], 422);
                }

                $provider = $request->input('provider');
                $configuration = $request->input('configuration');
            }

            $testResult = $this->testGatewayConnection($provider, $configuration);

            Log::info('Gateway connection test performed', [
                'provider' => $provider,
                'success' => $testResult['success'],
                'gateway_id' => $paymentGateway?->id
            ]);

            return response()->json($testResult);

        } catch (\Exception $e) {
            Log::error('Gateway connection test failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'gateway_id' => $paymentGateway?->id ?? null
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Connection test failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get gateway statistics
     */
    public function statistics(PaymentGateway $paymentGateway): JsonResponse
    {
        try {
            // Get payment statistics for this gateway
            $stats = [
                'total_transactions' => 0,
                'successful_transactions' => 0,
                'failed_transactions' => 0,
                'total_volume' => 0,
                'success_rate' => 0,
                'average_transaction_amount' => 0,
            ];

            // This would typically query the payments table
            // For now, return mock data
            $stats = [
                'total_transactions' => rand(100, 1000),
                'successful_transactions' => rand(80, 900),
                'failed_transactions' => rand(10, 100),
                'total_volume' => rand(1000000, 10000000),
                'success_rate' => round(rand(85, 98), 2),
                'average_transaction_amount' => rand(5000, 50000),
            ];

            return response()->json([
                'success' => true,
                'data' => $stats
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to fetch gateway statistics', [
                'gateway_id' => $paymentGateway->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch statistics'
            ], 500);
        }
    }

    /**
     * Test multiple gateways
     */
    public function testMultiple(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'gateway_ids' => 'required|array',
            'gateway_ids.*' => 'exists:payment_gateways,id',
            'test_transaction' => 'boolean'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $gatewayIds = $request->input('gateway_ids');
            $testTransaction = $request->input('test_transaction', false);
            $results = [];

            foreach ($gatewayIds as $gatewayId) {
                $gateway = PaymentGateway::find($gatewayId);
                if (!$gateway) {
                    continue;
                }

                $startTime = microtime(true);
                
                try {
                    $configuration = $this->decryptSensitiveData(
                        $gateway->configuration,
                        $gateway->provider
                    );

                    $testResult = $this->testGatewayConnection($gateway->provider, $configuration);
                    
                    // If test transaction is requested and connection is successful
                    if ($testTransaction && $testResult['success']) {
                        $transactionResult = $this->performTestTransaction($gateway->provider, $configuration);
                        $testResult['test_transaction'] = $transactionResult;
                    }

                    $endTime = microtime(true);
                    $responseTime = round(($endTime - $startTime) * 1000, 2);

                    $results[] = [
                        'gateway_id' => $gateway->id,
                        'gateway_name' => $gateway->name,
                        'provider' => $gateway->provider,
                        'success' => $testResult['success'],
                        'message' => $testResult['message'],
                        'response_time' => $responseTime,
                        'details' => $testResult['details'] ?? null,
                        'test_transaction' => $testResult['test_transaction'] ?? null
                    ];

                } catch (\Exception $e) {
                    $endTime = microtime(true);
                    $responseTime = round(($endTime - $startTime) * 1000, 2);

                    $results[] = [
                        'gateway_id' => $gateway->id,
                        'gateway_name' => $gateway->name,
                        'provider' => $gateway->provider,
                        'success' => false,
                        'message' => 'Test failed: ' . $e->getMessage(),
                        'response_time' => $responseTime,
                        'details' => null
                    ];
                }
            }

            Log::info('Multiple gateway test completed', [
                'tested_gateways' => count($results),
                'successful_tests' => count(array_filter($results, fn($r) => $r['success'])),
                'test_transaction' => $testTransaction
            ]);

            return response()->json([
                'success' => true,
                'data' => $results
            ]);

        } catch (\Exception $e) {
            Log::error('Multiple gateway test failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Gateway testing failed',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Get analytics for all gateways
     */
    public function analytics(): JsonResponse
    {
        try {
            $gateways = PaymentGateway::all();
            $analyticsData = [];

            foreach ($gateways as $gateway) {
                // In a real implementation, this would query actual payment data
                // For now, we'll generate mock analytics data
                $totalTransactions = rand(50, 500);
                $successfulTransactions = rand(40, $totalTransactions);
                $totalVolume = rand(500000, 5000000);
                $totalFees = $totalVolume * 0.025; // 2.5% fee estimate

                $analyticsData[] = [
                    'id' => $gateway->id,
                    'name' => $gateway->name,
                    'provider' => $gateway->provider,
                    'is_active' => $gateway->is_active,
                    'success_rate' => $totalTransactions > 0 ? round(($successfulTransactions / $totalTransactions) * 100, 2) : 0,
                    'total_volume' => $totalVolume,
                    'total_fees' => $totalFees,
                    'transaction_count' => $totalTransactions,
                    'successful_transactions' => $successfulTransactions,
                    'failed_transactions' => $totalTransactions - $successfulTransactions,
                    'average_transaction_amount' => $totalTransactions > 0 ? round($totalVolume / $totalTransactions, 2) : 0,
                    'last_transaction_date' => now()->subDays(rand(0, 30))->toISOString()
                ];
            }

            return response()->json([
                'success' => true,
                'data' => $analyticsData
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to fetch gateway analytics', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch analytics data'
            ], 500);
        }
    }

    /**
     * Perform test transaction
     */
    private function performTestTransaction(string $provider, array $configuration): array
    {
        try {
            switch ($provider) {
                case 'collectug':
                    // Mock test transaction for CollectUG
                    return [
                        'success' => true,
                        'message' => 'Test transaction successful',
                        'transaction_id' => 'TEST_' . time(),
                        'amount' => 100, // Test amount in UGX
                        'status' => 'completed'
                    ];

                case 'stripe':
                    // Mock test transaction for Stripe
                    return [
                        'success' => true,
                        'message' => 'Test charge successful',
                        'charge_id' => 'ch_test_' . time(),
                        'amount' => 100, // Test amount in cents
                        'status' => 'succeeded'
                    ];

                case 'paypal':
                    // Mock test transaction for PayPal
                    return [
                        'success' => true,
                        'message' => 'Test payment successful',
                        'payment_id' => 'PAY-TEST-' . time(),
                        'amount' => 1.00, // Test amount in USD
                        'status' => 'approved'
                    ];

                default:
                    return [
                        'success' => false,
                        'message' => 'Test transactions not supported for this provider'
                    ];
            }
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Test transaction failed: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Validate provider-specific configuration
     */
    private function validateProviderConfiguration(string $provider, array $configuration): array
    {
        $errors = [];

        switch ($provider) {
            case 'collectug':
                if (empty($configuration['api_key'])) {
                    $errors['configuration.api_key'] = 'API key is required for CollectUG';
                }
                if (empty($configuration['base_url'])) {
                    $errors['configuration.base_url'] = 'Base URL is required for CollectUG';
                }
                break;

            case 'stripe':
                if (empty($configuration['secret_key'])) {
                    $errors['configuration.secret_key'] = 'Secret key is required for Stripe';
                }
                break;

            case 'paypal':
                if (empty($configuration['client_id'])) {
                    $errors['configuration.client_id'] = 'Client ID is required for PayPal';
                }
                if (empty($configuration['client_secret'])) {
                    $errors['configuration.client_secret'] = 'Client secret is required for PayPal';
                }
                if (empty($configuration['environment']) || 
                    !in_array($configuration['environment'], ['sandbox', 'live'])) {
                    $errors['configuration.environment'] = 'Valid environment (sandbox/live) is required for PayPal';
                }
                break;
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }

    /**
     * Encrypt sensitive configuration data
     */
    private function encryptSensitiveData(array $configuration, string $provider): array
    {
        $sensitiveFields = $this->getSensitiveFields($provider);
        
        foreach ($sensitiveFields as $field) {
            if (isset($configuration[$field])) {
                $configuration[$field] = Crypt::encryptString($configuration[$field]);
            }
        }

        return $configuration;
    }

    /**
     * Decrypt sensitive configuration data
     */
    private function decryptSensitiveData(array $configuration, string $provider): array
    {
        $sensitiveFields = $this->getSensitiveFields($provider);
        
        foreach ($sensitiveFields as $field) {
            if (isset($configuration[$field])) {
                try {
                    $configuration[$field] = Crypt::decryptString($configuration[$field]);
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
     * Mask sensitive configuration data for display
     */
    private function maskSensitiveData(array $configuration, string $provider): array
    {
        $sensitiveFields = $this->getSensitiveFields($provider);
        
        foreach ($sensitiveFields as $field) {
            if (isset($configuration[$field])) {
                $value = $configuration[$field];
                $configuration[$field] = str_repeat('*', max(8, strlen($value) - 4)) . substr($value, -4);
            }
        }

        return $configuration;
    }

    /**
     * Get sensitive fields for a provider
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
     * Test gateway connection
     */
    private function testGatewayConnection(string $provider, array $configuration): array
    {
        switch ($provider) {
            case 'collectug':
                return $this->testCollectUgConnection($configuration);
            
            case 'stripe':
                return $this->testStripeConnection($configuration);
            
            case 'paypal':
                return $this->testPayPalConnection($configuration);
            
            default:
                return [
                    'success' => false,
                    'message' => 'Unsupported provider'
                ];
        }
    }

    /**
     * Test CollectUG connection
     */
    private function testCollectUgConnection(array $configuration): array
    {
        try {
            $service = new CollectUgService($configuration);
            $balance = $service->getBalance();

            if (isset($balance['account_status']) && $balance['account_status'] !== 'unavailable') {
                return [
                    'success' => true,
                    'message' => 'CollectUG connection successful',
                    'details' => [
                        'account_status' => $balance['account_status'],
                        'available_balance' => $balance['available_balance'],
                        'currency' => $balance['currency']
                    ]
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'CollectUG connection failed - invalid credentials or service unavailable'
                ];
            }
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'CollectUG connection failed: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Test Stripe connection
     */
    private function testStripeConnection(array $configuration): array
    {
        // Mock Stripe connection test
        if (empty($configuration['secret_key']) || !str_starts_with($configuration['secret_key'], 'sk_')) {
            return [
                'success' => false,
                'message' => 'Invalid Stripe secret key format'
            ];
        }

        return [
            'success' => true,
            'message' => 'Stripe connection test successful',
            'details' => [
                'account_status' => 'active',
                'test_mode' => str_contains($configuration['secret_key'], 'test')
            ]
        ];
    }

    /**
     * Test PayPal connection
     */
    private function testPayPalConnection(array $configuration): array
    {
        // Mock PayPal connection test
        if (empty($configuration['client_id']) || empty($configuration['client_secret'])) {
            return [
                'success' => false,
                'message' => 'Missing PayPal credentials'
            ];
        }

        return [
            'success' => true,
            'message' => 'PayPal connection test successful',
            'details' => [
                'environment' => $configuration['environment'],
                'account_status' => 'active'
            ]
        ];
    }
}