<?php

namespace App\Services\Sms;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SmsConfigurationService
{
    /**
     * Get SMS configuration for current tenant
     */
    public function getConfiguration(): array
    {
        $tenantId = $this->getCurrentTenantId();
        
        return Cache::remember("sms.config.{$tenantId}", 300, function () {
            // In a real implementation, this would come from database
            return [
                'enabled' => config('services.ugsms.enabled', true),
                'provider' => 'ugsms',
                'api_key' => config('services.ugsms.api_key'),
                'base_url' => config('services.ugsms.base_url', 'https://api.ugsms.com'),
                'sender_id' => config('services.ugsms.sender_id', 'BILLING'),
                'retry_attempts' => config('services.ugsms.retry_attempts', 2),
                'retry_delay' => config('services.ugsms.retry_delay', 500),
                'timeout' => config('services.ugsms.timeout', 15),
                'low_balance_threshold' => config('services.ugsms.low_balance_threshold', 5000),
                'cost_per_sms' => config('services.ugsms.cost_per_sms', 20),
                'max_message_length' => config('services.ugsms.max_message_length', 160),
                'unicode_support' => config('services.ugsms.unicode_support', true),
            ];
        });
    }

    /**
     * Update SMS configuration
     */
    public function updateConfiguration(array $config): bool
    {
        try {
            $tenantId = $this->getCurrentTenantId();
            
            // Validate configuration
            $validationErrors = $this->validateConfiguration($config);
            if (!empty($validationErrors)) {
                throw new \InvalidArgumentException('Configuration validation failed: ' . implode(', ', $validationErrors));
            }
            
            // Encrypt sensitive data
            if (isset($config['api_key'])) {
                $config['api_key_encrypted'] = Crypt::encryptString($config['api_key']);
                unset($config['api_key']); // Remove plain text key
            }
            
            // In a real implementation, save to database
            // For now, we'll update the cache and config
            Cache::forget("sms.config.{$tenantId}");
            
            // Update runtime config
            config([
                'services.ugsms.enabled' => $config['enabled'] ?? true,
                'services.ugsms.sender_id' => $config['sender_id'] ?? 'BILLING',
                'services.ugsms.retry_attempts' => $config['retry_attempts'] ?? 2,
                'services.ugsms.timeout' => $config['timeout'] ?? 15,
                'services.ugsms.low_balance_threshold' => $config['low_balance_threshold'] ?? 5000,
            ]);
            
            Log::info('SMS configuration updated', [
                'tenant_id' => $tenantId,
                'config_keys' => array_keys($config)
            ]);
            
            return true;
            
        } catch (\Exception $e) {
            Log::error('Failed to update SMS configuration', [
                'tenant_id' => $this->getCurrentTenantId(),
                'error' => $e->getMessage()
            ]);
            
            return false;
        }
    }

    /**
     * Test SMS configuration
     */
    public function testConfiguration(array $config = null): array
    {
        $config = $config ?? $this->getConfiguration();
        
        try {
            // Test API connectivity
            $response = Http::withHeaders([
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ])
            ->timeout($config['timeout'] ?? 15)
            ->post($config['base_url'] . '/api/v2/account/balance', [
                'api_key' => $config['api_key']
            ]);
            
            if ($response->successful()) {
                $data = $response->json();
                
                if ($data['success'] ?? false) {
                    return [
                        'success' => true,
                        'message' => 'SMS configuration test successful',
                        'balance' => $data['data']['remaining_balance'] ?? 0,
                        'response_time' => $response->transferStats?->getTransferTime() ?? 0
                    ];
                } else {
                    return [
                        'success' => false,
                        'message' => 'API returned error: ' . ($data['message'] ?? 'Unknown error'),
                        'error_code' => $data['error_code'] ?? null
                    ];
                }
            } else {
                return [
                    'success' => false,
                    'message' => 'HTTP error: ' . $response->status(),
                    'response_body' => $response->body()
                ];
            }
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Connection failed: ' . $e->getMessage(),
                'error_type' => get_class($e)
            ];
        }
    }

    /**
     * Validate SMS configuration
     */
    public function validateConfiguration(array $config): array
    {
        $errors = [];
        
        // Required fields
        $requiredFields = ['api_key', 'base_url'];
        foreach ($requiredFields as $field) {
            if (empty($config[$field])) {
                $errors[] = "Field '{$field}' is required";
            }
        }
        
        // Validate API key format
        if (isset($config['api_key'])) {
            if (strlen($config['api_key']) < 10) {
                $errors[] = 'API key must be at least 10 characters long';
            }
            
            if (!preg_match('/^[a-zA-Z0-9_-]+$/', $config['api_key'])) {
                $errors[] = 'API key contains invalid characters';
            }
        }
        
        // Validate URL
        if (isset($config['base_url'])) {
            if (!filter_var($config['base_url'], FILTER_VALIDATE_URL)) {
                $errors[] = 'Base URL is not a valid URL';
            }
            
            if (!str_starts_with($config['base_url'], 'https://')) {
                $errors[] = 'Base URL must use HTTPS';
            }
        }
        
        // Validate sender ID
        if (isset($config['sender_id'])) {
            if (strlen($config['sender_id']) > 11) {
                $errors[] = 'Sender ID cannot be longer than 11 characters';
            }
            
            if (!preg_match('/^[a-zA-Z0-9]+$/', $config['sender_id'])) {
                $errors[] = 'Sender ID can only contain letters and numbers';
            }
        }
        
        // Validate numeric fields
        $numericFields = [
            'retry_attempts' => [1, 5],
            'timeout' => [5, 60],
            'low_balance_threshold' => [0, 1000000],
            'cost_per_sms' => [1, 1000],
            'max_message_length' => [50, 1000]
        ];
        
        foreach ($numericFields as $field => $range) {
            if (isset($config[$field])) {
                $value = $config[$field];
                
                if (!is_numeric($value)) {
                    $errors[] = "Field '{$field}' must be numeric";
                } elseif ($value < $range[0] || $value > $range[1]) {
                    $errors[] = "Field '{$field}' must be between {$range[0]} and {$range[1]}";
                }
            }
        }
        
        return $errors;
    }

    /**
     * Get available sender IDs
     */
    public function getAvailableSenderIds(): array
    {
        return [
            'BILLING',
            'SUPPORT',
            'ALERTS',
            'VOUCHER',
            'PAYMENT',
            'SYSTEM'
        ];
    }

    /**
     * Mask API key for display
     */
    public function maskApiKey(string $apiKey): string
    {
        if (strlen($apiKey) <= 8) {
            return str_repeat('*', strlen($apiKey));
        }
        
        return substr($apiKey, 0, 4) . str_repeat('*', strlen($apiKey) - 8) . substr($apiKey, -4);
    }

    /**
     * Get current tenant ID
     */
    private function getCurrentTenantId(): ?string
    {
        // In a real implementation, this would get the current tenant from context
        return tenant('id') ?? 'default';
    }

    /**
     * Get SMS usage statistics
     */
    public function getUsageStatistics(): array
    {
        $tenantId = $this->getCurrentTenantId();
        
        return Cache::remember("sms.stats.{$tenantId}", 300, function () {
            // In a real implementation, this would query the database
            return [
                'today' => [
                    'sent' => 0,
                    'failed' => 0,
                    'cost' => 0
                ],
                'this_week' => [
                    'sent' => 0,
                    'failed' => 0,
                    'cost' => 0
                ],
                'this_month' => [
                    'sent' => 0,
                    'failed' => 0,
                    'cost' => 0
                ],
                'total' => [
                    'sent' => 0,
                    'failed' => 0,
                    'cost' => 0,
                    'success_rate' => 0
                ]
            ];
        });
    }

    /**
     * Check if SMS service is properly configured
     */
    public function isConfigured(): bool
    {
        $config = $this->getConfiguration();
        
        return !empty($config['api_key']) && 
               !empty($config['base_url']) && 
               $config['enabled'];
    }

    /**
     * Get configuration status
     */
    public function getConfigurationStatus(): array
    {
        $config = $this->getConfiguration();
        $isConfigured = $this->isConfigured();
        
        $status = [
            'configured' => $isConfigured,
            'enabled' => $config['enabled'] ?? false,
            'provider' => $config['provider'] ?? 'unknown',
            'sender_id' => $config['sender_id'] ?? null,
            'api_key_set' => !empty($config['api_key']),
            'api_key_masked' => !empty($config['api_key']) ? $this->maskApiKey($config['api_key']) : null,
            'base_url' => $config['base_url'] ?? null,
            'last_test' => Cache::get("sms.last_test.{$this->getCurrentTenantId()}"),
        ];
        
        if ($isConfigured) {
            $testResult = Cache::get("sms.test_result.{$this->getCurrentTenantId()}");
            if ($testResult) {
                $status['last_test_result'] = $testResult;
            }
        }
        
        return $status;
    }
}