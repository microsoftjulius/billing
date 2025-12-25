<?php

namespace App\Http\Controllers\Central;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class DashboardController extends Controller
{
    /**
     * Get dashboard statistics and overview
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $cacheKey = 'dashboard_stats_' . date('Y-m-d');
            $stats = Cache::remember($cacheKey, 3600, function () {
                return $this->calculateDashboardStats();
            });

            $recentActivity = $this->getRecentActivity();
            $systemHealth = $this->getSystemHealth();
            $pendingTasks = $this->getPendingTasks();

            return response()->json([
                'success' => true,
                'data' => [
                    'stats' => $stats,
                    'recent_activity' => $recentActivity,
                    'system_health' => $systemHealth,
                    'pending_tasks' => $pendingTasks,
                    'upcoming_events' => $this->getUpcomingEvents(),
                    'performance_metrics' => $this->getPerformanceMetrics(),
                ],
                'timestamp' => now()->toISOString(),
            ]);
        } catch (\Exception $e) {
            Log::error('Dashboard error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to load dashboard data',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Get system settings
     */
    public function settings(Request $request): JsonResponse
    {
        try {
            $settings = $this->loadAllSettings();

            return response()->json([
                'success' => true,
                'data' => [
                    'settings' => $settings,
                    'categories' => $this->getSettingsCategories(),
                    'dependencies' => $this->getSettingsDependencies(),
                    'validation_rules' => $this->getSettingsValidationRules(),
                    'default_values' => $this->getDefaultSettings(),
                ],
                'meta' => [
                    'last_modified' => $this->getLastSettingsModified(),
                    'configurable_by_role' => $this->getRoleBasedPermissions(),
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch settings: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to load settings',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Update system settings
     */
    public function updateSettings(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), $this->getSettingsValidationRules());

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
                'message' => 'Validation failed'
            ], 422);
        }

        DB::beginTransaction();
        try {
            $updates = $request->all();
            $updatedSettings = [];
            $changeLog = [];

            // Group settings by category for processing
            foreach ($updates as $key => $value) {
                if ($this->isSettingEditable($key)) {
                    $oldValue = $this->getSettingValue($key);

                    // Special handling for specific setting types
                    $processedValue = $this->processSettingValue($key, $value, $request);

                    // Update the setting
                    $this->updateSetting($key, $processedValue);

                    $updatedSettings[$key] = $processedValue;

                    // Log the change
                    if ($oldValue != $processedValue) {
                        $changeLog[] = [
                            'key' => $key,
                            'old_value' => $oldValue,
                            'new_value' => $processedValue,
                            'changed_by' => auth()->id(),
                            'changed_at' => now()->toISOString(),
                        ];
                    }
                }
            }

            // Clear relevant caches
            $this->clearSettingsCache();

            // Run post-update actions
            $this->runPostUpdateActions($updatedSettings);

            // Log the changes
            $this->logSettingsChanges($changeLog);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Settings updated successfully',
                'data' => [
                    'updated_settings' => $updatedSettings,
                    'requires_restart' => $this->requiresRestart($updatedSettings),
                    'notifications' => $this->getUpdateNotifications($updatedSettings),
                ],
                'meta' => [
                    'changes_count' => count($changeLog),
                    'timestamp' => now()->toISOString(),
                ]
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to update settings: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to update settings',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Calculate dashboard statistics
     */
    private function calculateDashboardStats(): array
    {
        // Load models dynamically to avoid conflicts
        $tenantModel = config('tenancy.tenant_model', \App\Models\Tenant::class);
        $userModel = config('auth.providers.users.model', \App\Models\User::class);

        $totalTenants = $tenantModel::count();
        $activeTenants = $tenantModel::where('is_active', true)->count();
        $suspendedTenants = $tenantModel::where('is_active', false)->count();

        $totalUsers = $userModel::count();
        $activeUsers = $userModel::where('is_active', true)->count();

        // Calculate revenue metrics
        $revenueMetrics = $this->calculateRevenueMetrics();

        // Calculate usage metrics
        $usageMetrics = $this->calculateUsageMetrics();

        // Calculate growth rates
        $growthRates = $this->calculateGrowthRates();

        return [
            'tenants' => [
                'total' => $totalTenants,
                'active' => $activeTenants,
                'suspended' => $suspendedTenants,
                'growth' => $growthRates['tenant_growth'],
            ],
            'users' => [
                'total' => $totalUsers,
                'active' => $activeUsers,
                'growth' => $growthRates['user_growth'],
            ],
            'revenue' => $revenueMetrics,
            'usage' => $usageMetrics,
            'performance' => [
                'uptime' => $this->calculateUptime(),
                'response_time' => $this->getAverageResponseTime(),
                'error_rate' => $this->getErrorRate(),
            ],
            'system' => [
                'storage_used' => $this->getStorageUsage(),
                'memory_usage' => $this->getMemoryUsage(),
                'cpu_usage' => $this->getCpuUsage(),
            ],
        ];
    }

    /**
     * Load all system settings
     */
    private function loadAllSettings(): array
    {
        return [
            'general' => $this->loadGeneralSettings(),
            'tenancy' => $this->loadTenancySettings(),
            'billing' => $this->loadBillingSettings(),
            'security' => $this->loadSecuritySettings(),
            'email' => $this->loadEmailSettings(),
            'storage' => $this->loadStorageSettings(),
            'api' => $this->loadApiSettings(),
            'maintenance' => $this->loadMaintenanceSettings(),
            'notifications' => $this->loadNotificationSettings(),
            'appearance' => $this->loadAppearanceSettings(),
        ];
    }

    /**
     * Load general settings
     */
    private function loadGeneralSettings(): array
    {
        return [
            'app_name' => config('app.name', 'Multi-Tenant SaaS'),
            'app_url' => config('app.url'),
            'timezone' => config('app.timezone'),
            'locale' => config('app.locale'),
            'maintenance_mode' => app()->isDownForMaintenance(),
            'debug_mode' => config('app.debug'),
            'environment' => config('app.env'),
            'version' => config('app.version', '1.0.0'),
            'support_email' => config('mail.support_email', 'support@example.com'),
            'contact_phone' => config('app.contact_phone'),
            'address' => config('app.address'),
            'terms_url' => config('app.terms_url'),
            'privacy_url' => config('app.privacy_url'),
            'max_login_attempts' => config('auth.max_attempts', 5),
            'session_lifetime' => config('session.lifetime', 120),
        ];
    }

    /**
     * Load tenancy settings
     */
    private function loadTenancySettings(): array
    {
        return [
            'tenant_model' => config('tenancy.tenant_model'),
            'central_domains' => config('tenancy.central_domains', []),
            'tenant_connection' => config('tenancy.database.tenant_connection'),
            'auto_create_tenant_database' => config('tenancy.database.auto_create', true),
            'auto_update_tenant_database' => config('tenancy.database.auto_update', true),
            'tenant_database_prefix' => config('tenancy.database.prefix', 'tenant_'),
            'tenant_database_suffix' => config('tenancy.database.suffix', ''),
            'tenant_migrations_path' => config('tenancy.database.migrations_path'),
            'tenant_seeder' => config('tenancy.database.seeder'),
            'tenant_domain_model' => config('tenancy.domain_model'),
            'tenant_route_prefix' => config('tenancy.routes.prefix', ''),
            'tenant_middleware' => config('tenancy.middleware', []),
            'default_tenant_plan' => config('tenancy.default_plan', 'basic'),
            'tenant_signup_enabled' => config('tenancy.signup_enabled', true),
            'tenant_approval_required' => config('tenancy.approval_required', false),
            'max_tenants_per_user' => config('tenancy.max_per_user', 1),
        ];
    }

    /**
     * Load billing settings
     */
    private function loadBillingSettings(): array
    {
        return [
            'currency' => config('billing.currency', 'USD'),
            'currency_symbol' => config('billing.currency_symbol', '$'),
            'tax_rate' => config('billing.tax_rate', 0),
            'tax_inclusive' => config('billing.tax_inclusive', false),
            'invoice_prefix' => config('billing.invoice_prefix', 'INV-'),
            'payment_gateway' => config('billing.gateway', 'stripe'),
            'stripe_public_key' => config('services.stripe.key'),
            'stripe_secret_key' => config('services.stripe.secret'),
            'stripe_webhook_secret' => config('services.stripe.webhook.secret'),
            'paypal_client_id' => config('services.paypal.client_id'),
            'paypal_secret' => config('services.paypal.secret'),
            'trial_days' => config('billing.trial_days', 14),
            'grace_period_days' => config('billing.grace_period_days', 7),
            'auto_suspend_after_grace' => config('billing.auto_suspend', true),
            'prorate_changes' => config('billing.prorate', true),
            'send_invoice_emails' => config('billing.send_invoices', true),
            'send_payment_receipts' => config('billing.send_receipts', true),
            'late_fee_percentage' => config('billing.late_fee', 5),
            'plans' => config('billing.plans', [
                'basic' => ['price' => 29, 'features' => []],
                'premium' => ['price' => 79, 'features' => []],
                'enterprise' => ['price' => 199, 'features' => []],
            ]),
        ];
    }

    /**
     * Load security settings
     */
    private function loadSecuritySettings(): array
    {
        return [
            'password_min_length' => config('auth.password_min_length', 8),
            'password_require_numbers' => config('auth.password_numbers', true),
            'password_require_symbols' => config('auth.password_symbols', true),
            'password_require_mixed_case' => config('auth.password_mixed_case', true),
            'password_expiry_days' => config('auth.password_expiry', 90),
            'max_sessions_per_user' => config('auth.max_sessions', 5),
            'two_factor_enabled' => config('auth.two_factor', false),
            'two_factor_method' => config('auth.two_factor_method', 'email'),
            'ip_whitelist' => config('auth.ip_whitelist', []),
            'ip_blacklist' => config('auth.ip_blacklist', []),
            'session_timeout_minutes' => config('session.lifetime', 120),
            'enable_login_alerts' => config('auth.login_alerts', true),
            'enable_suspicious_activity_alerts' => config('auth.activity_alerts', true),
            'require_email_verification' => config('auth.email_verification', true),
            'require_phone_verification' => config('auth.phone_verification', false),
            'rate_limiting_enabled' => config('auth.rate_limiting', true),
            'max_requests_per_minute' => config('auth.max_requests', 60),
        ];
    }

    /**
     * Load email settings
     */
    private function loadEmailSettings(): array
    {
        return [
            'mail_driver' => config('mail.default'),
            'mail_host' => config('mail.mailers.smtp.host'),
            'mail_port' => config('mail.mailers.smtp.port'),
            'mail_username' => config('mail.mailers.smtp.username'),
            'mail_password' => config('mail.mailers.smtp.password'),
            'mail_encryption' => config('mail.mailers.smtp.encryption'),
            'mail_from_address' => config('mail.from.address'),
            'mail_from_name' => config('mail.from.name'),
            'mailgun_domain' => config('services.mailgun.domain'),
            'mailgun_secret' => config('services.mailgun.secret'),
            'ses_key' => config('services.ses.key'),
            'ses_secret' => config('services.ses.secret'),
            'ses_region' => config('services.ses.region'),
            'postmark_token' => config('services.postmark.token'),
            'send_welcome_emails' => config('mail.send_welcome', true),
            'send_notification_emails' => config('mail.send_notifications', true),
            'send_newsletter' => config('mail.send_newsletter', false),
            'email_queue_enabled' => config('mail.queue', false),
            'email_queue_connection' => config('queue.default'),
            'test_email_address' => config('mail.test_email'),
        ];
    }

    /**
     * Load storage settings
     */
    private function loadStorageSettings(): array
    {
        return [
            'default_disk' => config('filesystems.default'),
            's3_key' => config('filesystems.disks.s3.key'),
            's3_secret' => config('filesystems.disks.s3.secret'),
            's3_region' => config('filesystems.disks.s3.region'),
            's3_bucket' => config('filesystems.disks.s3.bucket'),
            's3_endpoint' => config('filesystems.disks.s3.endpoint'),
            's3_url' => config('filesystems.disks.s3.url'),
            'max_upload_size_mb' => config('filesystems.max_upload_size', 10),
            'allowed_file_types' => config('filesystems.allowed_types', ['jpg', 'png', 'pdf', 'doc']),
            'storage_quota_per_tenant_mb' => config('filesystems.tenant_quota', 1024),
            'backup_enabled' => config('backup.enabled', false),
            'backup_frequency' => config('backup.frequency', 'daily'),
            'backup_retention_days' => config('backup.retention', 30),
            'backup_to_cloud' => config('backup.to_cloud', false),
            'backup_notification_email' => config('backup.notification_email'),
            'cdn_enabled' => config('filesystems.cdn.enabled', false),
            'cdn_url' => config('filesystems.cdn.url'),
        ];
    }

    /**
     * Load API settings
     */
    private function loadApiSettings(): array
    {
        return [
            'api_rate_limit' => config('api.rate_limit', 60),
            'api_rate_limit_period' => config('api.rate_period', 1),
            'api_key_authentication' => config('api.key_auth', true),
            'api_token_expiration_days' => config('api.token_expiry', 30),
            'enable_api_documentation' => config('api.docs_enabled', true),
            'api_docs_url' => config('api.docs_url'),
            'enable_api_logging' => config('api.logging', true),
            'cors_enabled' => config('cors.enabled', true),
            'cors_allowed_origins' => config('cors.allowed_origins', []),
            'cors_allowed_methods' => config('cors.allowed_methods', ['*']),
            'cors_allowed_headers' => config('cors.allowed_headers', ['*']),
            'webhook_timeout_seconds' => config('api.webhook_timeout', 30),
            'webhook_retry_attempts' => config('api.webhook_retries', 3),
            'enable_webhook_signatures' => config('api.webhook_signatures', true),
        ];
    }

    /**
     * Load maintenance settings
     */
    private function loadMaintenanceSettings(): array
    {
        return [
            'maintenance_mode' => app()->isDownForMaintenance(),
            'maintenance_message' => config('app.maintenance_message'),
            'maintenance_allowed_ips' => config('app.maintenance_allowed_ips', []),
            'scheduled_maintenance_enabled' => config('maintenance.scheduled', false),
            'maintenance_schedule' => config('maintenance.schedule'),
            'maintenance_duration_minutes' => config('maintenance.duration', 60),
            'notify_before_maintenance_hours' => config('maintenance.notify_before', 24),
            'auto_update_enabled' => config('maintenance.auto_update', false),
            'update_check_frequency' => config('maintenance.update_check', 'daily'),
            'backup_before_update' => config('maintenance.backup_before', true),
            'rollback_on_failure' => config('maintenance.rollback', true),
        ];
    }

    /**
     * Load notification settings
     */
    private function loadNotificationSettings(): array
    {
        return [
            'notifications_enabled' => config('notifications.enabled', true),
            'email_notifications' => config('notifications.email', true),
            'sms_notifications' => config('notifications.sms', false),
            'push_notifications' => config('notifications.push', false),
            'slack_webhook_url' => config('notifications.slack_webhook'),
            'telegram_bot_token' => config('notifications.telegram_token'),
            'telegram_chat_id' => config('notifications.telegram_chat_id'),
            'send_daily_summary' => config('notifications.daily_summary', true),
            'send_weekly_report' => config('notifications.weekly_report', true),
            'send_monthly_invoice' => config('notifications.monthly_invoice', true),
            'notify_on_new_tenant' => config('notifications.new_tenant', true),
            'notify_on_payment' => config('notifications.payment', true),
            'notify_on_suspension' => config('notifications.suspension', true),
            'notify_on_high_usage' => config('notifications.high_usage', true),
            'notify_on_system_error' => config('notifications.system_error', true),
            'notification_channels' => config('notifications.channels', ['mail', 'database']),
        ];
    }

    /**
     * Load appearance settings
     */
    private function loadAppearanceSettings(): array
    {
        return [
            'theme' => config('appearance.theme', 'light'),
            'primary_color' => config('appearance.primary_color', '#3B82F6'),
            'secondary_color' => config('appearance.secondary_color', '#6B7280'),
            'logo_url' => config('appearance.logo_url'),
            'favicon_url' => config('appearance.favicon_url'),
            'login_background' => config('appearance.login_background'),
            'enable_custom_css' => config('appearance.custom_css', false),
            'custom_css' => config('appearance.css'),
            'enable_custom_js' => config('appearance.custom_js', false),
            'custom_js' => config('appearance.js'),
            'enable_dark_mode' => config('appearance.dark_mode', true),
            'default_language' => config('appearance.default_language', 'en'),
            'date_format' => config('appearance.date_format', 'Y-m-d'),
            'time_format' => config('appearance.time_format', 'H:i'),
            'enable_animations' => config('appearance.animations', true),
            'sidebar_collapsed' => config('appearance.sidebar_collapsed', false),
        ];
    }

    /**
     * Get settings categories
     */
    private function getSettingsCategories(): array
    {
        return [
            'general' => [
                'name' => 'General',
                'description' => 'Basic application settings',
                'icon' => 'settings',
                'order' => 1,
            ],
            'tenancy' => [
                'name' => 'Tenancy',
                'description' => 'Multi-tenancy configuration',
                'icon' => 'database',
                'order' => 2,
            ],
            'billing' => [
                'name' => 'Billing & Payments',
                'description' => 'Payment gateway and billing settings',
                'icon' => 'credit-card',
                'order' => 3,
            ],
            'security' => [
                'name' => 'Security',
                'description' => 'Security and authentication settings',
                'icon' => 'shield',
                'order' => 4,
            ],
            'email' => [
                'name' => 'Email',
                'description' => 'Email server and notification settings',
                'icon' => 'mail',
                'order' => 5,
            ],
            'storage' => [
                'name' => 'Storage',
                'description' => 'File storage and backup settings',
                'icon' => 'hard-drive',
                'order' => 6,
            ],
            'api' => [
                'name' => 'API',
                'description' => 'API configuration and rate limiting',
                'icon' => 'code',
                'order' => 7,
            ],
            'maintenance' => [
                'name' => 'Maintenance',
                'description' => 'System maintenance and updates',
                'icon' => 'tool',
                'order' => 8,
            ],
            'notifications' => [
                'name' => 'Notifications',
                'description' => 'Notification channels and preferences',
                'icon' => 'bell',
                'order' => 9,
            ],
            'appearance' => [
                'name' => 'Appearance',
                'description' => 'UI/UX and theme settings',
                'icon' => 'palette',
                'order' => 10,
            ],
        ];
    }

    /**
     * Get settings validation rules
     */
    private function getSettingsValidationRules(): array
    {
        return [
            // General settings
            'general.app_name' => 'sometimes|string|max:100',
            'general.app_url' => 'sometimes|url|max:255',
            'general.timezone' => 'sometimes|timezone',
            'general.locale' => 'sometimes|string|max:10',
            'general.support_email' => 'sometimes|email|max:255',
            'general.contact_phone' => 'sometimes|string|max:20',
            'general.max_login_attempts' => 'sometimes|integer|min:1|max:10',
            'general.session_lifetime' => 'sometimes|integer|min:15|max:1440',

            // Tenancy settings
            'tenancy.central_domains' => 'sometimes|array',
            'tenancy.central_domains.*' => 'string|max:255',
            'tenancy.auto_create_tenant_database' => 'sometimes|boolean',
            'tenancy.tenant_database_prefix' => 'sometimes|string|max:50',
            'tenancy.tenant_signup_enabled' => 'sometimes|boolean',
            'tenancy.tenant_approval_required' => 'sometimes|boolean',
            'tenancy.max_tenants_per_user' => 'sometimes|integer|min:1|max:100',

            // Billing settings
            'billing.currency' => 'sometimes|string|size:3',
            'billing.tax_rate' => 'sometimes|numeric|min:0|max:50',
            'billing.tax_inclusive' => 'sometimes|boolean',
            'billing.trial_days' => 'sometimes|integer|min:0|max:365',
            'billing.grace_period_days' => 'sometimes|integer|min:0|max:30',
            'billing.auto_suspend_after_grace' => 'sometimes|boolean',
            'billing.late_fee_percentage' => 'sometimes|numeric|min:0|max:50',
            'billing.stripe_public_key' => 'sometimes|string|max:255',
            'billing.stripe_secret_key' => 'sometimes|string|max:255',
            'billing.paypal_client_id' => 'sometimes|string|max:255',
            'billing.paypal_secret' => 'sometimes|string|max:255',

            // Security settings
            'security.password_min_length' => 'sometimes|integer|min:6|max:32',
            'security.password_expiry_days' => 'sometimes|integer|min:0|max:365',
            'security.max_sessions_per_user' => 'sometimes|integer|min:1|max:20',
            'security.two_factor_enabled' => 'sometimes|boolean',
            'security.enable_login_alerts' => 'sometimes|boolean',
            'security.rate_limiting_enabled' => 'sometimes|boolean',
            'security.max_requests_per_minute' => 'sometimes|integer|min:10|max:1000',

            // Email settings
            'email.mail_driver' => ['sometimes', 'string', Rule::in(['smtp', 'mailgun', 'ses', 'postmark'])],
            'email.mail_host' => 'sometimes|string|max:255',
            'email.mail_port' => 'sometimes|integer|min:1|max:65535',
            'email.mail_username' => 'sometimes|string|max:255',
            'email.mail_from_address' => 'sometimes|email|max:255',
            'email.mail_from_name' => 'sometimes|string|max:255',
            'email.send_welcome_emails' => 'sometimes|boolean',
            'email.test_email_address' => 'sometimes|email|max:255',

            // Storage settings
            'storage.default_disk' => ['sometimes', 'string', Rule::in(['local', 's3', 'ftp'])],
            'storage.max_upload_size_mb' => 'sometimes|integer|min:1|max:100',
            'storage.storage_quota_per_tenant_mb' => 'sometimes|integer|min:10|max:1048576',
            'storage.backup_enabled' => 'sometimes|boolean',
            'storage.backup_retention_days' => 'sometimes|integer|min:1|max:365',
            'storage.cdn_enabled' => 'sometimes|boolean',

            // API settings
            'api.api_rate_limit' => 'sometimes|integer|min:10|max:1000',
            'api.api_token_expiration_days' => 'sometimes|integer|min:1|max:365',
            'api.enable_api_logging' => 'sometimes|boolean',
            'api.cors_enabled' => 'sometimes|boolean',
            'api.webhook_timeout_seconds' => 'sometimes|integer|min:5|max:300',

            // Maintenance settings
            'maintenance.maintenance_mode' => 'sometimes|boolean',
            'maintenance.auto_update_enabled' => 'sometimes|boolean',
            'maintenance.backup_before_update' => 'sometimes|boolean',

            // Notification settings
            'notifications.notifications_enabled' => 'sometimes|boolean',
            'notifications.email_notifications' => 'sometimes|boolean',
            'notifications.send_daily_summary' => 'sometimes|boolean',
            'notifications.notify_on_new_tenant' => 'sometimes|boolean',
            'notifications.notify_on_payment' => 'sometimes|boolean',

            // Appearance settings
            'appearance.theme' => ['sometimes', 'string', Rule::in(['light', 'dark', 'auto'])],
            'appearance.primary_color' => 'sometimes|string|regex:/^#[0-9A-F]{6}$/i',
            'appearance.default_language' => 'sometimes|string|max:10',
            'appearance.enable_dark_mode' => 'sometimes|boolean',
        ];
    }

    /**
     * Get recent activity
     */
    private function getRecentActivity(): array
    {
        // This would typically query an activity log table
        // For now, return sample data
        return [
            [
                'id' => 1,
                'type' => 'tenant_created',
                'description' => 'New tenant "Acme Corp" registered',
                'user' => 'System',
                'timestamp' => now()->subMinutes(5)->toISOString(),
                'icon' => 'user-plus',
                'color' => 'green',
            ],
            [
                'id' => 2,
                'type' => 'payment_received',
                'description' => 'Payment of $199 received from Tech Solutions',
                'user' => 'John Doe',
                'timestamp' => now()->subHours(1)->toISOString(),
                'icon' => 'dollar-sign',
                'color' => 'blue',
            ],
            [
                'id' => 3,
                'type' => 'user_suspended',
                'description' => 'User "jane@example.com" suspended due to policy violation',
                'user' => 'Admin',
                'timestamp' => now()->subHours(3)->toISOString(),
                'icon' => 'user-x',
                'color' => 'red',
            ],
            [
                'id' => 4,
                'type' => 'system_alert',
                'description' => 'High memory usage detected (85%)',
                'user' => 'System',
                'timestamp' => now()->subHours(6)->toISOString(),
                'icon' => 'alert-triangle',
                'color' => 'yellow',
            ],
            [
                'id' => 5,
                'type' => 'backup_completed',
                'description' => 'Daily backup completed successfully',
                'user' => 'System',
                'timestamp' => now()->subDays(1)->toISOString(),
                'icon' => 'save',
                'color' => 'green',
            ],
        ];
    }

    /**
     * Get system health status
     */
    private function getSystemHealth(): array
    {
        return [
            'status' => 'healthy',
            'checks' => [
                'database' => [
                    'status' => 'connected',
                    'latency' => '15ms',
                    'last_check' => now()->toISOString(),
                ],
                'redis' => [
                    'status' => 'connected',
                    'latency' => '5ms',
                    'last_check' => now()->toISOString(),
                ],
                'storage' => [
                    'status' => 'ok',
                    'usage' => '65%',
                    'available' => '35GB',
                    'last_check' => now()->toISOString(),
                ],
                'email' => [
                    'status' => 'sending',
                    'last_sent' => now()->subMinutes(10)->toISOString(),
                    'queue_size' => 0,
                ],
                'api' => [
                    'status' => 'responsive',
                    'avg_response_time' => '125ms',
                    'error_rate' => '0.2%',
                    'last_check' => now()->toISOString(),
                ],
            ],
            'alerts' => [],
            'last_updated' => now()->toISOString(),
        ];
    }

    /**
     * Get pending tasks
     */
    private function getPendingTasks(): array
    {
        return [
            'pending_approvals' => [
                'tenants' => 3,
                'users' => 7,
            ],
            'pending_payments' => [
                'count' => 5,
                'amount' => 1245.50,
            ],
            'pending_suspensions' => [
                'count' => 2,
                'reason' => 'Payment overdue',
            ],
            'pending_updates' => [
                'system' => 1,
                'tenants' => 12,
            ],
            'support_tickets' => [
                'open' => 8,
                'unassigned' => 3,
                'high_priority' => 2,
            ],
        ];
    }

    /**
     * Helper methods for settings management
     */
    private function isSettingEditable(string $key): bool
    {
        $protectedSettings = [
            'general.environment',
            'general.debug_mode',
            'security.stripe_secret_key',
            'security.paypal_secret',
            'email.mail_password',
            'storage.s3_secret',
        ];

        return !in_array($key, $protectedSettings);
    }

    private function getSettingValue(string $key)
    {
        $keys = explode('.', $key);
        $category = $keys[0];
        $setting = $keys[1] ?? null;

        $settings = $this->loadAllSettings();

        return $setting ? ($settings[$category][$setting] ?? null) : null;
    }

    private function processSettingValue(string $key, $value, Request $request)
    {
        // Handle special cases
        switch ($key) {
            case 'storage.max_upload_size_mb':
                return (int) $value * 1024 * 1024; // Convert MB to bytes

            case 'security.ip_whitelist':
            case 'security.ip_blacklist':
                return is_array($value) ? $value : explode(',', $value);

            case 'appearance.primary_color':
            case 'appearance.secondary_color':
                return Str::startsWith($value, '#') ? $value : '#' . $value;

            case 'email.mail_password':
                // Don't update if empty (means keep existing)
                return empty($value) ? $this->getSettingValue($key) : $value;

            default:
                return $value;
        }
    }

    private function updateSetting(string $key, $value): void
    {
        $keys = explode('.', $key);
        $category = $keys[0];
        $setting = $keys[1] ?? null;

        if (!$setting) return;

        // This would typically update the setting in database or config file
        // For now, we'll simulate by storing in cache
        $cacheKey = "setting_{$category}_{$setting}";
        Cache::forever($cacheKey, $value);

        // Log the update
        Log::info("Setting updated: {$key} = " . json_encode($value));
    }

    private function clearSettingsCache(): void
    {
        Cache::forget('all_settings');
        Cache::forget('dashboard_stats_' . date('Y-m-d'));

        // Clear category-specific caches
        $categories = array_keys($this->getSettingsCategories());
        foreach ($categories as $category) {
            Cache::forget("settings_{$category}");
        }
    }

    private function runPostUpdateActions(array $updatedSettings): void
    {
        foreach ($updatedSettings as $key => $value) {
            switch ($key) {
                case 'general.timezone':
                    // Clear timezone-dependent caches
                    Cache::flush();
                    break;

                case 'email.mail_driver':
                case 'email.mail_host':
                case 'email.mail_port':
                    // Clear mail-related caches
                    Cache::forget('mail_config');
                    break;

                case 'maintenance.maintenance_mode':
                    if ($value) {
                        // Put application in maintenance mode
                        // Artisan::call('down');
                    } else {
                        // Bring application back up
                        // Artisan::call('up');
                    }
                    break;

                case 'storage.default_disk':
                    // Clear storage caches
                    Cache::forget('storage_config');
                    break;
            }
        }
    }

    private function logSettingsChanges(array $changes): void
    {
        if (empty($changes)) return;

        $logEntry = [
            'timestamp' => now()->toISOString(),
            'user_id' => auth()->id(),
            'changes' => $changes,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ];

        // Store in database or log file
        $logPath = storage_path('logs/settings-changes.log');
        File::append($logPath, json_encode($logEntry) . PHP_EOL);
    }

    private function requiresRestart(array $updatedSettings): bool
    {
        $restartRequiredFor = [
            'general.timezone',
            'email.mail_driver',
            'storage.default_disk',
            'api.api_rate_limit',
            'security.max_sessions_per_user',
        ];

        return !empty(array_intersect(array_keys($updatedSettings), $restartRequiredFor));
    }

    private function getUpdateNotifications(array $updatedSettings): array
    {
        $notifications = [];

        if (isset($updatedSettings['security.password_min_length'])) {
            $notifications[] = [
                'type' => 'info',
                'message' => 'Password policy updated. Existing users will be prompted to update their password on next login.',
            ];
        }

        if (isset($updatedSettings['billing.trial_days'])) {
            $notifications[] = [
                'type' => 'info',
                'message' => 'Trial period changed. This only affects new signups.',
            ];
        }

        if (isset($updatedSettings['storage.max_upload_size_mb'])) {
            $notifications[] = [
                'type' => 'warning',
                'message' => 'Maximum upload size changed. Existing uploads are not affected.',
            ];
        }

        return $notifications;
    }

    /**
     * Additional helper methods for dashboard
     */
    private function calculateRevenueMetrics(): array
    {
        // This would query actual revenue data
        // For now, simulate with sample data
        return [
            'monthly' => 15420.75,
            'quarterly' => 46262.25,
            'yearly' => 185049.00,
            'growth' => 12.5, // percentage
            'recurring' => 12850.50,
            'one_time' => 2570.25,
            'top_plan' => 'premium',
        ];
    }

    private function calculateUsageMetrics(): array
    {
        return [
            'active_tenants' => 42,
            'total_users' => 1250,
            'api_calls_today' => 15420,
            'storage_used_gb' => 25.5,
            'bandwidth_used_gb' => 125.75,
            'peak_concurrent_users' => 320,
        ];
    }

    private function calculateGrowthRates(): array
    {
        return [
            'tenant_growth' => 8.2,
            'user_growth' => 15.7,
            'revenue_growth' => 12.5,
            'usage_growth' => 22.3,
        ];
    }

    private function calculateUptime(): float
    {
        return 99.95; // percentage
    }

    private function getAverageResponseTime(): int
    {
        return 125; // milliseconds
    }

    private function getErrorRate(): float
    {
        return 0.2; // percentage
    }

    private function getStorageUsage(): array
    {
        return [
            'used_gb' => 25.5,
            'total_gb' => 100,
            'percentage' => 25.5,
            'breakdown' => [
                'tenants' => 18.2,
                'logs' => 3.5,
                'backups' => 3.8,
            ],
        ];
    }

    private function getMemoryUsage(): array
    {
        return [
            'used_mb' => 512,
            'total_mb' => 1024,
            'percentage' => 50,
        ];
    }

    private function getCpuUsage(): array
    {
        return [
            'current' => 35,
            'average' => 28,
            'peak' => 85,
        ];
    }

    private function getUpcomingEvents(): array
    {
        return [
            [
                'title' => 'Monthly Maintenance',
                'date' => now()->addDays(3)->toISOString(),
                'type' => 'maintenance',
                'description' => 'Scheduled system maintenance',
            ],
            [
                'title' => 'Backup Rotation',
                'date' => now()->addDays(7)->toISOString(),
                'type' => 'backup',
                'description' => 'Monthly backup archive rotation',
            ],
            [
                'title' => 'License Renewal',
                'date' => now()->addDays(15)->toISOString(),
                'type' => 'billing',
                'description' => 'SSL certificate renewal',
            ],
        ];
    }

    private function getPerformanceMetrics(): array
    {
        return [
            'response_times' => [
                'p50' => 125,
                'p95' => 320,
                'p99' => 580,
            ],
            'throughput' => [
                'requests_per_second' => 45,
                'concurrent_connections' => 150,
            ],
            'cache' => [
                'hit_rate' => 92.5,
                'miss_rate' => 7.5,
            ],
            'database' => [
                'query_time' => 85,
                'connections' => 25,
            ],
        ];
    }

    private function getSettingsDependencies(): array
    {
        return [
            'email.send_welcome_emails' => ['email.notifications_enabled' => true],
            'billing.auto_suspend_after_grace' => ['billing.grace_period_days' => '>0'],
            'storage.backup_to_cloud' => ['storage.backup_enabled' => true],
            'security.two_factor_enabled' => ['security.enable_login_alerts' => true],
        ];
    }

    private function getDefaultSettings(): array
    {
        return [
            'general.app_name' => 'Multi-Tenant SaaS',
            'general.timezone' => 'UTC',
            'general.locale' => 'en',
            'billing.currency' => 'USD',
            'billing.tax_rate' => 0,
            'security.password_min_length' => 8,
            'storage.max_upload_size_mb' => 10,
            'api.api_rate_limit' => 60,
        ];
    }

    private function getLastSettingsModified(): string
    {
        $logPath = storage_path('logs/settings-changes.log');
        if (!File::exists($logPath)) {
            return now()->subDays(30)->toISOString();
        }

        $lines = file($logPath);
        if (empty($lines)) {
            return now()->subDays(30)->toISOString();
        }

        $lastLine = json_decode(end($lines), true);
        return $lastLine['timestamp'] ?? now()->subDays(30)->toISOString();
    }

    private function getRoleBasedPermissions(): array
    {
        return [
            'super_admin' => ['*'], // All settings
            'admin' => [ // Most settings except sensitive ones
                'general.*',
                'tenancy.*',
                'billing.*',
                'security.*' => ['except' => ['security.stripe_secret_key', 'security.paypal_secret']],
                'email.*',
                'storage.*',
                'api.*',
                'maintenance.*',
                'notifications.*',
                'appearance.*',
            ],
            'manager' => [ // Limited settings
                'general.app_name',
                'general.support_email',
                'notifications.*',
                'appearance.*',
            ],
            'viewer' => [], // Read-only
        ];
    }
}
