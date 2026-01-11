<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\TenantSetting;
use App\Services\Payment\CollectUgService;
use App\Services\Router\MikrotikService;
use App\Services\SmsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Cache;

class SettingController extends Controller
{
    private ?Tenant $currentTenant;
    private CollectUgService $paymentService;
    private MikrotikService $routerService;
    private SmsService $smsService;

    public function __construct(
        CollectUgService $paymentService,
        MikrotikService $routerService,
        SmsService $smsService
    ) {
        $this->currentTenant = $this->resolveTenant();
        $this->paymentService = $paymentService;
        $this->routerService = $routerService;
        $this->smsService = $smsService;
    }

    /**
     * Resolve the current tenant from the request
     */
    private function resolveTenant(): ?Tenant
    {
        // Method 1: From header (for API calls)
        if (request()->hasHeader('X-Tenant-ID')) {
            return Tenant::where('uuid', request()->header('X-Tenant-ID'))->first();
        }

        // Method 2: From subdomain (for web calls)
        $host = request()->getHost();
        $subdomain = explode('.', $host)[0];

        if ($subdomain && $subdomain !== 'www' && $subdomain !== 'api') {
            return Tenant::where('subdomain', $subdomain)->first();
        }

        // Method 3: From request parameter (for shared routes)
        if (request()->has('tenant_id')) {
            return Tenant::where('uuid', request()->get('tenant_id'))->first();
        }

        // Method 4: From authenticated user (if applicable)
        if (auth()->check() && method_exists(auth()->user(), 'tenant')) {
            return auth()->user()->tenant;
        }

        return null; // No tenant resolved
    }

    /**
     * Get tenant settings
     */
    public function index(Request $request): JsonResponse
    {
        try {
            // Check if user is admin for this tenant
            if (!$this->isTenantAdmin()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized. Admin access required.'
                ], 403);
            }

            // Get all settings for this tenant
            $settings = TenantSetting::where('tenant_id', $this->currentTenant->id)
                ->get()
                ->mapWithKeys(function ($setting) {
                    return [$setting->key => $setting->value];
                })
                ->toArray();

            // Get default settings structure
            $defaultSettings = $this->getDefaultSettings();

            // Merge with defaults
            $allSettings = array_merge($defaultSettings, $settings);

            // Group settings by category
            $groupedSettings = $this->groupSettingsByCategory($allSettings);

            // Mask sensitive settings for API response
            $maskedSettings = $this->maskSensitiveSettingsInGroups($groupedSettings);

            // Get system information
            $systemInfo = $this->getSystemInformation();

            return response()->json([
                'success' => true,
                'tenant' => [
                    'id' => $this->currentTenant->id,
                    'code' => $this->currentTenant->code,
                    'name' => $this->currentTenant->name,
                ],
                'data' => [
                    'settings' => $maskedSettings,
                    'system_info' => $systemInfo,
                    'service_status' => $this->getServiceStatus(),
                    'last_updated' => TenantSetting::where('tenant_id', $this->currentTenant->id)
                        ->max('updated_at')?->toISOString(),
                ]
            ]);

        } catch (\Exception $e) {
            Log::channel('settings')->error('Failed to fetch tenant settings', [
                'tenant_id' => $this->currentTenant?->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch settings',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Update tenant settings
     */
    public function update(Request $request): JsonResponse
    {
        try {
            // Check if user is admin for this tenant
            if (!$this->isTenantAdmin()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized. Admin access required.'
                ], 403);
            }

            // Validate settings structure
            $validator = Validator::make($request->all(), [
                'settings' => 'required|array',
                'settings.payment' => 'nullable|array',
                'settings.payment.api_key' => 'nullable|string|min:10|max:255',
                'settings.payment.api_secret' => 'nullable|string|min:10|max:255',
                'settings.payment.callback_url' => 'nullable|url',
                'settings.payment.enabled' => 'boolean',
                'settings.payment.test_mode' => 'boolean',
                'settings.sms' => 'nullable|array',
                'settings.sms.provider' => 'nullable|string|in:africas_talking,smpp,custom,ugsms',
                'settings.sms.api_key' => 'nullable|string|min:10|max:255',
                'settings.sms.username' => 'nullable|string',
                'settings.sms.short_code' => 'nullable|string|max:20',
                'settings.sms.enabled' => 'boolean',
                'settings.router' => 'nullable|array',
                'settings.router.host' => 'nullable|string',
                'settings.router.port' => 'nullable|integer|min:1|max:65535',
                'settings.router.username' => 'nullable|string',
                'settings.router.password' => 'nullable|string|min:6|max:255',
                'settings.router.ssl' => 'boolean',
                'settings.router.enabled' => 'boolean',
                'settings.general' => 'nullable|array',
                'settings.general.tenant_name' => 'nullable|string|max:255',
                'settings.general.currency' => 'nullable|string|size:3',
                'settings.general.timezone' => 'nullable|timezone',
                'settings.general.date_format' => 'nullable|string',
                'settings.general.enable_notifications' => 'boolean',
                'settings.general.maintenance_mode' => 'boolean',
                'settings.vouchers' => 'nullable|array',
                'settings.vouchers.default_validity_hours' => 'nullable|integer|min:1|max:720',
                'settings.vouchers.auto_expire' => 'boolean',
                'settings.vouchers.send_sms_on_create' => 'boolean',
                'settings.vouchers.sms_template' => 'nullable|string|max:500',
                'settings.security' => 'nullable|array',
                'settings.security.require_2fa' => 'boolean',
                'settings.security.password_policy' => 'nullable|string|in:low,medium,high',
                'settings.security.session_timeout' => 'nullable|integer|min:5|max:1440',
                'settings.security.max_login_attempts' => 'nullable|integer|min:1|max:10',
            ]);

            if ($validator->fails()) {
                throw new ValidationException($validator);
            }

            $settings = $request->input('settings');
            $updatedSettings = [];
            $failedUpdates = [];

            // Process each setting category
            foreach ($settings as $category => $categorySettings) {
                foreach ($categorySettings as $key => $value) {
                    $fullKey = $category . '.' . $key;

                    try {
                        // Validate API key format for sensitive keys
                        if ($this->isSensitiveKey($fullKey) && !empty($value)) {
                            if (!$this->validateApiKeyFormat($fullKey, $value)) {
                                throw new \InvalidArgumentException("Invalid API key format for {$fullKey}");
                            }
                        }

                        // Update or create setting
                        $setting = TenantSetting::updateOrCreate(
                            [
                                'tenant_id' => $this->currentTenant->id,
                                'key' => $fullKey,
                            ],
                            [
                                'value' => $value,
                                'data_type' => $this->getDataType($value),
                                'updated_by' => auth()->id(),
                            ]
                        );

                        $updatedSettings[$fullKey] = $value;

                        Log::channel('settings')->debug('Setting updated', [
                            'tenant_id' => $this->currentTenant->id,
                            'key' => $fullKey,
                            'value' => $this->isSensitiveKey($fullKey) ? '***MASKED***' : $value,
                            'updated_by' => auth()->id(),
                        ]);

                    } catch (\Exception $e) {
                        $failedUpdates[] = [
                            'key' => $fullKey,
                            'error' => $e->getMessage(),
                        ];

                        Log::channel('settings')->error('Failed to update setting', [
                            'tenant_id' => $this->currentTenant->id,
                            'key' => $fullKey,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            }

            // Clear settings cache
            Cache::forget('tenant_settings_' . $this->currentTenant->id);

            // Log the update
            Log::channel('settings')->info('Tenant settings updated', [
                'tenant_id' => $this->currentTenant->id,
                'total_updated' => count($updatedSettings),
                'failed_updates' => count($failedUpdates),
                'updated_by' => auth()->id(),
            ]);

            $response = [
                'success' => true,
                'message' => 'Settings updated successfully',
                'data' => [
                    'updated_settings' => $this->maskSensitiveSettings($updatedSettings),
                    'failed_updates' => $failedUpdates,
                    'total_updated' => count($updatedSettings),
                    'timestamp' => now()->toISOString(),
                ]
            ];

            if (!empty($failedUpdates)) {
                $response['message'] = 'Settings updated with some errors';
                $response['warnings'] = $failedUpdates;
            }

            return response()->json($response);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);

        } catch (\Exception $e) {
            Log::channel('settings')->error('Failed to update settings', [
                'tenant_id' => $this->currentTenant?->id,
                'error' => $e->getMessage(),
                'settings' => $request->input('settings')
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update settings',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Test SMS configuration
     */
    public function testSms(Request $request): JsonResponse
    {
        try {
            // Check if user is admin for this tenant
            if (!$this->isTenantAdmin()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized. Admin access required.'
                ], 403);
            }

            // Validate request
            $validator = Validator::make($request->all(), [
                'phone_number' => 'required|string|max:20',
                'message' => 'nullable|string|max:160',
                'use_current_settings' => 'boolean',
                'test_settings' => 'nullable|array',
                'test_settings.provider' => 'required_with:test_settings|string|in:africas_talking,smpp,custom',
                'test_settings.api_key' => 'required_with:test_settings|string|min:10',
                'test_settings.username' => 'nullable|string',
                'test_settings.short_code' => 'nullable|string|max:20',
            ]);

            if ($validator->fails()) {
                throw new ValidationException($validator);
            }

            $phoneNumber = $request->input('phone_number');
            $message = $request->input('message', 'Test SMS from ' . $this->currentTenant->name);
            $useCurrentSettings = $request->input('use_current_settings', true);
            $testSettings = $request->input('test_settings');

            $testResult = [
                'phone_number' => $phoneNumber,
                'message' => $message,
                'use_current_settings' => $useCurrentSettings,
                'timestamp' => now()->toISOString(),
            ];

            if ($useCurrentSettings) {
                // Use current SMS settings from database
                $smsSettings = $this->getSmsSettings();
                $testResult['settings_used'] = 'current';
                $testResult['settings'] = $smsSettings;

                if (empty($smsSettings['api_key']) || !$smsSettings['enabled']) {
                    throw new \Exception('SMS is not configured or disabled');
                }

                // Test with current settings
                $sent = $this->smsService->sendTestMessage($phoneNumber, $message);

                if ($sent) {
                    $testResult['status'] = 'success';
                    $testResult['message_id'] = $sent['message_id'] ?? null;
                    $testResult['provider_response'] = $sent['response'] ?? null;
                } else {
                    $testResult['status'] = 'failed';
                    $testResult['error'] = 'Failed to send SMS';
                }

            } else {
                // Use test settings from request
                $testResult['settings_used'] = 'test';
                $testResult['settings'] = $testSettings;

                // Create a temporary SMS service with test settings
                $tempSmsService = new \App\Services\SmsService($testSettings);
                $sent = $tempSmsService->sendTestMessage($phoneNumber, $message);

                if ($sent) {
                    $testResult['status'] = 'success';
                    $testResult['message_id'] = $sent['message_id'] ?? null;
                    $testResult['provider_response'] = $sent['response'] ?? null;
                } else {
                    $testResult['status'] = 'failed';
                    $testResult['error'] = 'Failed to send SMS with test settings';
                }
            }

            Log::channel('settings')->info('SMS test completed', [
                'tenant_id' => $this->currentTenant->id,
                'phone_number' => $phoneNumber,
                'status' => $testResult['status'],
                'test_type' => $testResult['settings_used'],
                'tested_by' => auth()->id(),
            ]);

            return response()->json([
                'success' => $testResult['status'] === 'success',
                'message' => $testResult['status'] === 'success'
                    ? 'SMS test completed successfully'
                    : 'SMS test failed',
                'data' => $testResult
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);

        } catch (\Exception $e) {
            Log::channel('settings')->error('SMS test failed', [
                'tenant_id' => $this->currentTenant?->id,
                'error' => $e->getMessage(),
                'request' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'SMS test failed: ' . $e->getMessage(),
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Test payment gateway configuration
     */
    public function testPayment(Request $request): JsonResponse
    {
        try {
            // Check if user is admin for this tenant
            if (!$this->isTenantAdmin()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized. Admin access required.'
                ], 403);
            }

            // Validate request
            $validator = Validator::make($request->all(), [
                'amount' => 'nullable|numeric|min:100|max:50000',
                'phone_number' => 'required|string|max:20',
                'use_current_settings' => 'boolean',
                'test_settings' => 'nullable|array',
                'test_settings.api_key' => 'required_with:test_settings|string|min:10',
                'test_settings.api_secret' => 'required_with:test_settings|string|min:10',
                'test_settings.base_url' => 'required_with:test_settings|url',
                'test_settings.callback_url' => 'nullable|url',
            ]);

            if ($validator->fails()) {
                throw new ValidationException($validator);
            }

            $amount = $request->input('amount', 1000); // Default 1000 UGX
            $phoneNumber = $request->input('phone_number');
            $useCurrentSettings = $request->input('use_current_settings', true);
            $testSettings = $request->input('test_settings');

            $testResult = [
                'amount' => $amount,
                'phone_number' => $phoneNumber,
                'use_current_settings' => $useCurrentSettings,
                'timestamp' => now()->toISOString(),
                'test_type' => 'connection_test', // Not actual payment
            ];

            if ($useCurrentSettings) {
                // Use current payment settings from database
                $paymentSettings = $this->getPaymentSettings();
                $testResult['settings_used'] = 'current';
                $testResult['settings'] = $paymentSettings;

                if (empty($paymentSettings['api_key']) || !$paymentSettings['enabled']) {
                    throw new \Exception('Payment gateway is not configured or disabled');
                }

                // Test connection only (no actual payment)
                $connectionTest = $this->paymentService->testConnection();

                if ($connectionTest) {
                    // Get balance as additional test
                    $balance = $this->paymentService->getBalance();

                    $testResult['status'] = 'success';
                    $testResult['connection_test'] = 'passed';
                    $testResult['balance'] = $balance;
                    $testResult['supported_currencies'] = $this->paymentService->getSupportedCurrencies();
                } else {
                    $testResult['status'] = 'failed';
                    $testResult['error'] = 'Failed to connect to payment gateway';
                }

            } else {
                // Use test settings from request
                $testResult['settings_used'] = 'test';
                $testResult['settings'] = $testSettings;

                // Create a temporary payment service with test settings
                $tempPaymentService = new \App\Services\Payment\CollectUgService($testSettings);
                $connectionTest = $tempPaymentService->testConnection();

                if ($connectionTest) {
                    $balance = $tempPaymentService->getBalance();

                    $testResult['status'] = 'success';
                    $testResult['connection_test'] = 'passed';
                    $testResult['balance'] = $balance;
                    $testResult['supported_currencies'] = $tempPaymentService->getSupportedCurrencies();
                } else {
                    $testResult['status'] = 'failed';
                    $testResult['error'] = 'Failed to connect to payment gateway with test settings';
                }
            }

            Log::channel('settings')->info('Payment gateway test completed', [
                'tenant_id' => $this->currentTenant->id,
                'status' => $testResult['status'],
                'test_type' => $testResult['settings_used'],
                'tested_by' => auth()->id(),
            ]);

            return response()->json([
                'success' => $testResult['status'] === 'success',
                'message' => $testResult['status'] === 'success'
                    ? 'Payment gateway test completed successfully'
                    : 'Payment gateway test failed',
                'data' => $testResult
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);

        } catch (\Exception $e) {
            Log::channel('settings')->error('Payment gateway test failed', [
                'tenant_id' => $this->currentTenant?->id,
                'error' => $e->getMessage(),
                'request' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Payment gateway test failed: ' . $e->getMessage(),
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Test router configuration
     */
    public function testRouter(Request $request): JsonResponse
    {
        try {
            // Check if user is admin for this tenant
            if (!$this->isTenantAdmin()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized. Admin access required.'
                ], 403);
            }

            // Validate request
            $validator = Validator::make($request->all(), [
                'use_current_settings' => 'boolean',
                'test_settings' => 'nullable|array',
                'test_settings.host' => 'required_with:test_settings|string',
                'test_settings.port' => 'required_with:test_settings|integer|min:1|max:65535',
                'test_settings.username' => 'required_with:test_settings|string',
                'test_settings.password' => 'required_with:test_settings|string',
                'test_settings.ssl' => 'boolean',
                'test_settings.timeout' => 'nullable|integer|min:1|max:30',
            ]);

            if ($validator->fails()) {
                throw new ValidationException($validator);
            }

            $useCurrentSettings = $request->input('use_current_settings', true);
            $testSettings = $request->input('test_settings');

            $testResult = [
                'use_current_settings' => $useCurrentSettings,
                'timestamp' => now()->toISOString(),
            ];

            if ($useCurrentSettings) {
                // Use current router settings from database
                $routerSettings = $this->getRouterSettings();
                $testResult['settings_used'] = 'current';
                $testResult['settings'] = $routerSettings;

                if (empty($routerSettings['host']) || !$routerSettings['enabled']) {
                    throw new \Exception('Router is not configured or disabled');
                }

                // Test connection
                $connectionTest = $this->routerService->testConnection();

                if ($connectionTest) {
                    // Get router info as additional test
                    $routerInfo = $this->routerService->getSystemResources();
                    $activeUsers = $this->routerService->getActiveUsersCount();
                    $totalUsers = $this->routerService->getTotalUsersCount();

                    $testResult['status'] = 'success';
                    $testResult['connection_test'] = 'passed';
                    $testResult['router_info'] = [
                        'model' => $routerInfo['model'] ?? 'unknown',
                        'firmware_version' => $routerInfo['firmware_version'] ?? 'unknown',
                        'uptime' => $routerInfo['uptime_formatted'] ?? 'unknown',
                    ];
                    $testResult['user_stats'] = [
                        'active_users' => $activeUsers,
                        'total_users' => $totalUsers,
                    ];
                    $testResult['system_resources'] = [
                        'cpu_load' => $routerInfo['cpu_load_formatted'] ?? '0%',
                        'memory_usage' => $routerInfo['memory_usage_formatted'] ?? '0%',
                        'disk_usage' => $routerInfo['disk_usage_formatted'] ?? '0%',
                    ];
                } else {
                    $testResult['status'] = 'failed';
                    $testResult['error'] = 'Failed to connect to router';
                }

            } else {
                // Use test settings from request
                $testResult['settings_used'] = 'test';
                $testResult['settings'] = $testSettings;

                // Create a temporary router service with test settings
                $tempRouterService = new \App\Services\Router\MikrotikService($testSettings);
                $connectionTest = $tempRouterService->testConnection();

                if ($connectionTest) {
                    $routerInfo = $tempRouterService->getSystemResources();

                    $testResult['status'] = 'success';
                    $testResult['connection_test'] = 'passed';
                    $testResult['router_info'] = [
                        'model' => $routerInfo['model'] ?? 'unknown',
                        'firmware_version' => $routerInfo['firmware_version'] ?? 'unknown',
                        'uptime' => $routerInfo['uptime_formatted'] ?? 'unknown',
                    ];
                } else {
                    $testResult['status'] = 'failed';
                    $testResult['error'] = 'Failed to connect to router with test settings';
                }
            }

            Log::channel('settings')->info('Router test completed', [
                'tenant_id' => $this->currentTenant->id,
                'status' => $testResult['status'],
                'test_type' => $testResult['settings_used'],
                'tested_by' => auth()->id(),
            ]);

            return response()->json([
                'success' => $testResult['status'] === 'success',
                'message' => $testResult['status'] === 'success'
                    ? 'Router test completed successfully'
                    : 'Router test failed',
                'data' => $testResult
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);

        } catch (\Exception $e) {
            Log::channel('settings')->error('Router test failed', [
                'tenant_id' => $this->currentTenant?->id,
                'error' => $e->getMessage(),
                'request' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Router test failed: ' . $e->getMessage(),
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Check if current user is a tenant admin
     */
    private function isTenantAdmin(): bool
    {
        if (!$this->currentTenant) {
            return false;
        }

        // Check if user is authenticated
        if (!auth()->check()) {
            return false;
        }

        $user = auth()->user();

        // Check if user belongs to this tenant
        if ($user->tenant_id !== $this->currentTenant->id) {
            return false;
        }

        // Check if user has admin role for this tenant
        return $user->hasRole('admin') || $user->hasRole('super-admin');
    }

    /**
     * Get default settings structure
     */
    private function getDefaultSettings(): array
    {
        return [
            // Payment settings
            'payment.api_key' => '',
            'payment.api_secret' => '',
            'payment.base_url' => 'https://api.collect.ug',
            'payment.callback_url' => route('api.payments.callback'),
            'payment.enabled' => false,
            'payment.test_mode' => true,

            // SMS settings
            'sms.provider' => 'africas_talking',
            'sms.api_key' => '',
            'sms.username' => '',
            'sms.short_code' => '',
            'sms.enabled' => false,

            // Router settings
            'router.host' => '192.168.88.1',
            'router.port' => 8728,
            'router.username' => 'admin',
            'router.password' => '',
            'router.ssl' => false,
            'router.timeout' => 10,
            'router.enabled' => false,

            // General settings
            'general.tenant_name' => $this->currentTenant->name,
            'general.currency' => 'UGX',
            'general.timezone' => 'Africa/Kampala',
            'general.date_format' => 'Y-m-d H:i:s',
            'general.enable_notifications' => true,
            'general.maintenance_mode' => false,

            // Voucher settings
            'vouchers.default_validity_hours' => 24,
            'vouchers.auto_expire' => true,
            'vouchers.send_sms_on_create' => true,
            'vouchers.sms_template' => 'Your voucher code: {code}. Password: {password}. Expires: {expires_at}.',

            // Security settings
            'security.require_2fa' => false,
            'security.password_policy' => 'medium',
            'security.session_timeout' => 60,
            'security.max_login_attempts' => 5,
        ];
    }

    /**
     * Group settings by category
     */
    private function groupSettingsByCategory(array $settings): array
    {
        $grouped = [];

        foreach ($settings as $key => $value) {
            $parts = explode('.', $key, 2);

            if (count($parts) === 2) {
                $category = $parts[0];
                $settingKey = $parts[1];

                if (!isset($grouped[$category])) {
                    $grouped[$category] = [];
                }

                $grouped[$category][$settingKey] = $value;
            }
        }

        return $grouped;
    }

    /**
     * Get data type for a value
     */
    private function getDataType($value): string
    {
        if (is_bool($value)) return 'boolean';
        if (is_int($value)) return 'integer';
        if (is_float($value)) return 'float';
        if (is_array($value)) return 'array';
        if (is_string($value)) return 'string';
        return 'string';
    }

    /**
     * Get system information
     */
    private function getSystemInformation(): array
    {
        return [
            'app' => [
                'name' => config('app.name'),
                'env' => config('app.env'),
                'debug' => config('app.debug'),
                'url' => config('app.url'),
                'version' => '1.0.0', // Should come from a config file or constant
            ],
            'server' => [
                'php_version' => PHP_VERSION,
                'laravel_version' => app()->version(),
                'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
                'server_name' => $_SERVER['SERVER_NAME'] ?? 'Unknown',
                'server_addr' => $_SERVER['SERVER_ADDR'] ?? 'Unknown',
            ],
            'database' => [
                'driver' => config('database.default'),
                'version' => $this->getDatabaseVersion(),
            ],
            'tenant' => [
                'created_at' => $this->currentTenant->created_at->toISOString(),
                'customers_count' => \App\Models\Customer::where('tenant_id', $this->currentTenant->id)->count(),
                'payments_count' => \App\Models\Payment::where('tenant_id', $this->currentTenant->id)->count(),
                'vouchers_count' => \App\Models\Voucher::where('tenant_id', $this->currentTenant->id)->count(),
                'users_count' => \App\Models\User::where('tenant_id', $this->currentTenant->id)->count(),
            ],
        ];
    }

    /**
     * Get database version
     */
    private function getDatabaseVersion(): string
    {
        try {
            $results = \DB::select('select version() as version');
            return $results[0]->version ?? 'Unknown';
        } catch (\Exception $e) {
            return 'Unknown';
        }
    }

    /**
     * Get service status
     */
    private function getServiceStatus(): array
    {
        return [
            'payment_gateway' => $this->testPaymentService(),
            'sms_service' => $this->testSmsService(),
            'router_service' => $this->testRouterService(),
            'database' => $this->testDatabase(),
            'cache' => $this->testCache(),
            'queue' => $this->testQueue(),
        ];
    }

    /**
     * Test payment service
     */
    private function testPaymentService(): array
    {
        try {
            $settings = $this->getPaymentSettings();

            return [
                'status' => $settings['enabled'] ? 'enabled' : 'disabled',
                'configured' => !empty($settings['api_key']) && !empty($settings['api_secret']),
                'last_test' => $settings['last_test'] ?? null,
                'test_result' => $settings['last_test_result'] ?? null,
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'configured' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Test SMS service
     */
    private function testSmsService(): array
    {
        try {
            $settings = $this->getSmsSettings();

            return [
                'status' => $settings['enabled'] ? 'enabled' : 'disabled',
                'configured' => !empty($settings['api_key']),
                'provider' => $settings['provider'] ?? 'not_configured',
                'last_test' => $settings['last_test'] ?? null,
                'test_result' => $settings['last_test_result'] ?? null,
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'configured' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Test router service
     */
    private function testRouterService(): array
    {
        try {
            $settings = $this->getRouterSettings();

            return [
                'status' => $settings['enabled'] ? 'enabled' : 'disabled',
                'configured' => !empty($settings['host']) && !empty($settings['username']) && !empty($settings['password']),
                'last_test' => $settings['last_test'] ?? null,
                'test_result' => $settings['last_test_result'] ?? null,
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'configured' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Test database connection
     */
    private function testDatabase(): array
    {
        try {
            \DB::connection()->getPdo();
            return [
                'status' => 'connected',
                'connection' => 'ok',
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'disconnected',
                'connection' => 'failed',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Test cache
     */
    private function testCache(): array
    {
        try {
            $key = 'cache_test_' . time();
            $value = 'test_value';

            \Cache::put($key, $value, 10);
            $retrieved = \Cache::get($key);

            return [
                'status' => $retrieved === $value ? 'working' : 'failed',
                'driver' => config('cache.default'),
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'failed',
                'driver' => config('cache.default'),
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Test queue
     */
    private function testQueue(): array
    {
        try {
            // Simple queue test
            return [
                'status' => 'unknown', // Would need actual queue test
                'driver' => config('queue.default'),
                'connection' => config('queue.connections.' . config('queue.default') . '.connection'),
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'failed',
                'driver' => config('queue.default'),
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get payment settings
     */
    private function getPaymentSettings(): array
    {
        return Cache::remember('tenant_payment_settings_' . $this->currentTenant->id, 300, function () {
            $settings = TenantSetting::where('tenant_id', $this->currentTenant->id)
                ->where('key', 'like', 'payment.%')
                ->get()
                ->mapWithKeys(function ($setting) {
                    $key = str_replace('payment.', '', $setting->key);
                    return [$key => $setting->value];
                })
                ->toArray();

            return array_merge([
                'api_key' => '',
                'api_secret' => '',
                'base_url' => 'https://api.collect.ug',
                'callback_url' => route('api.payments.callback'),
                'enabled' => false,
                'test_mode' => true,
            ], $settings);
        });
    }

    /**
     * Get SMS settings
     */
    private function getSmsSettings(): array
    {
        return Cache::remember('tenant_sms_settings_' . $this->currentTenant->id, 300, function () {
            $settings = TenantSetting::where('tenant_id', $this->currentTenant->id)
                ->where('key', 'like', 'sms.%')
                ->get()
                ->mapWithKeys(function ($setting) {
                    $key = str_replace('sms.', '', $setting->key);
                    return [$key => $setting->value];
                })
                ->toArray();

            return array_merge([
                'provider' => 'africas_talking',
                'api_key' => '',
                'username' => '',
                'short_code' => '',
                'enabled' => false,
            ], $settings);
        });
    }

    /**
     * Get router settings
     */
    private function getRouterSettings(): array
    {
        return Cache::remember('tenant_router_settings_' . $this->currentTenant->id, 300, function () {
            $settings = TenantSetting::where('tenant_id', $this->currentTenant->id)
                ->where('key', 'like', 'router.%')
                ->get()
                ->mapWithKeys(function ($setting) {
                    $key = str_replace('router.', '', $setting->key);
                    return [$key => $setting->value];
                })
                ->toArray();

            return array_merge([
                'host' => '192.168.88.1',
                'port' => 8728,
                'username' => 'admin',
                'password' => '',
                'ssl' => false,
                'timeout' => 10,
                'enabled' => false,
            ], $settings);
        });
    }

    /**
     * Check if a key contains sensitive information
     */
    private function isSensitiveKey(string $key): bool
    {
        $sensitivePatterns = [
            'api_key',
            'api_secret',
            'password',
            'secret',
            'token',
            'private_key',
        ];

        foreach ($sensitivePatterns as $pattern) {
            if (str_contains(strtolower($key), $pattern)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Validate API key format
     */
    private function validateApiKeyFormat(string $key, string $value): bool
    {
        // Basic validation rules
        if (strlen($value) < 10 || strlen($value) > 255) {
            return false;
        }

        // Check for invalid characters (spaces, special chars except underscore and dash)
        if (!preg_match('/^[a-zA-Z0-9_\-]+$/', $value)) {
            return false;
        }

        // Service-specific validation
        if (str_contains($key, 'payment')) {
            return $this->validatePaymentApiKey($value);
        }

        if (str_contains($key, 'sms')) {
            return $this->validateSmsApiKey($value);
        }

        return true;
    }

    /**
     * Validate payment API key format
     */
    private function validatePaymentApiKey(string $value): bool
    {
        // CollectUG API keys typically start with pk_ or sk_
        $validPrefixes = ['pk_', 'sk_', 'api_', 'collect_'];
        
        foreach ($validPrefixes as $prefix) {
            if (str_starts_with($value, $prefix)) {
                // Must have content after prefix
                return strlen($value) > strlen($prefix) + 5;
            }
        }

        // Allow generic API keys without specific prefix
        return strlen($value) >= 16;
    }

    /**
     * Validate SMS API key format
     */
    private function validateSmsApiKey(string $value): bool
    {
        // UGSMS and other SMS providers
        $validPrefixes = ['sms_', 'ugsms_', 'AT_', 'api_'];
        
        foreach ($validPrefixes as $prefix) {
            if (str_starts_with($value, $prefix)) {
                // Must have content after prefix
                return strlen($value) > strlen($prefix) + 5;
            }
        }

        // Allow generic API keys without specific prefix
        return strlen($value) >= 12;
    }

    /**
     * Mask sensitive settings for API response
     */
    private function maskSensitiveSettings(array $settings): array
    {
        $masked = [];
        
        foreach ($settings as $key => $value) {
            if ($this->isSensitiveKey($key)) {
                $masked[$key] = $this->maskApiKey($value);
            } else {
                $masked[$key] = $value;
            }
        }

        return $masked;
    }

    /**
     * Mask API key for display
     */
    private function maskApiKey(string $value): string
    {
        if (strlen($value) <= 8) {
            // For short values, show first 2 and last 2 characters
            return substr($value, 0, 2) . str_repeat('*', max(1, strlen($value) - 4)) . substr($value, -2);
        } else {
            // For longer values, show first 4 and last 4 characters
            return substr($value, 0, 4) . str_repeat('*', max(1, strlen($value) - 8)) . substr($value, -4);
        }
    }

    /**
     * Mask sensitive settings in grouped format
     */
    private function maskSensitiveSettingsInGroups(array $groupedSettings): array
    {
        $masked = [];
        
        foreach ($groupedSettings as $category => $settings) {
            $masked[$category] = [];
            foreach ($settings as $key => $value) {
                $fullKey = $category . '.' . $key;
                if ($this->isSensitiveKey($fullKey)) {
                    $masked[$category][$key] = $this->maskApiKey($value);
                } else {
                    $masked[$category][$key] = $value;
                }
            }
        }

        return $masked;
    }
}
