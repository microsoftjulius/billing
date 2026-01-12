<?php

namespace App\Services;

use App\DTOs\Router\VoucherDTO;
use App\Models\Payment;
use App\Models\Voucher as VoucherModel;
use App\Services\Router\MikrotikService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class VoucherService
{
    private MikrotikService $mikrotik;
    private SmsService $smsService;

    public function __construct(MikrotikService $mikrotik, SmsService $smsService)
    {
        $this->mikrotik = $mikrotik;
        $this->smsService = $smsService;
    }

    public function generateVoucher(Payment $payment): VoucherModel
    {
        DB::beginTransaction();

        try {
            // Generate unique voucher code
            $code = $this->generateUniqueCode();
            $password = Str::random(8);

            // Determine package details
            $package = $payment->metadata['package'] ?? 'daily_1gb';
            $validityHours = $payment->metadata['validity_hours'] ?? $this->getValidityHours($package);
            $profile = $this->getProfileName($package);
            $dataLimitMB = $this->getDataLimit($package);

            // Create voucher DTO for router
            $voucherDTO = VoucherDTO::create(
                code: $code,
                password: $password,
                profile: $profile,
                validityHours: $validityHours,
                dataLimitMB: $dataLimitMB,
                price: $payment->amount,
                currency: $payment->currency,
                customerName: $payment->customer->name,
                customerPhone: $payment->customer->phone,
                customerEmail: $payment->customer->email,
                createdAt: now(),
                expiresAt: now()->addHours($validityHours),
                metadata: [
                    'payment_id' => $payment->id,
                    'customer_id' => $payment->customer_id,
                    'package' => $package
                ]
            );

            // Create voucher on MikroTik FIRST
            $voucherCreated = $this->mikrotik->createVoucher($voucherDTO);

            if (!$voucherCreated) {
                throw new \Exception('Failed to create voucher on MikroTik router');
            }

            // Create voucher in database
            $voucher = VoucherModel::create([
                'uuid' => Str::orderedUuid(),
                'customer_id' => $payment->customer_id,
                'payment_id' => $payment->id,
                'code' => $code,
                'password' => $password,
                'profile' => $profile,
                'validity_hours' => $validityHours,
                'data_limit_mb' => $dataLimitMB,
                'price' => $payment->amount,
                'currency' => $payment->currency,
                'status' => 'active',
                'activated_at' => now(),
                'expires_at' => now()->addHours($validityHours),
                'router_metadata' => [
                    'created_on_router' => true,
                    'created_at' => now()->toISOString()
                ],
                'metadata' => [
                    'package' => $package,
                    'generated_at' => now()->toISOString(),
                    'payment_transaction_id' => $payment->transaction_id,
                    'validity_hours' => $validityHours
                ]
            ]);

            // Send voucher via SMS
            $smsSent = $this->smsService->sendVoucher($payment->customer->phone, $voucher);

            if (!$smsSent) {
                Log::channel('voucher')->warning('SMS sending failed, but voucher created', [
                    'voucher_id' => $voucher->id,
                    'phone' => $payment->customer->phone
                ]);
            }

            DB::commit();

            Log::channel('voucher')->info('Voucher generated successfully', [
                'voucher_id' => $voucher->id,
                'payment_id' => $payment->id,
                'customer_id' => $payment->customer_id,
                'code' => $code,
                'profile' => $profile,
                'validity_hours' => $validityHours
            ]);

            return $voucher;

        } catch (\Exception $e) {
            DB::rollBack();

            Log::channel('voucher')->error('Voucher generation failed', [
                'payment_id' => $payment->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            throw $e;
        }
    }

    private function generateUniqueCode(): string
    {
        do {
            // Format: BIL-XXXX-XXXX where X is alphanumeric
            $code = 'BIL-' . strtoupper(Str::random(4)) . '-' . strtoupper(Str::random(4));
        } while (VoucherModel::where('code', $code)->exists());

        return $code;
    }

    private function getValidityHours(string $package): int
    {
        return match($package) {
            'daily_1gb' => 24,
            'weekly_5gb' => 168,      // 7 days
            'monthly_20gb' => 720,    // 30 days
            'unlimited_daily' => 24,
            'unlimited_weekly' => 168,
            'unlimited_monthly' => 720,
            'daily' => 24,
            'weekly' => 168,
            'monthly' => 720,
            default => 24
        };
    }

    private function getProfileName(string $package): string
    {
        return match($package) {
            'daily_1gb' => '1GB-DAILY',
            'weekly_5gb' => '5GB-WEEKLY',
            'monthly_20gb' => '20GB-MONTHLY',
            'unlimited_daily' => 'UNLIMITED-DAILY',
            'unlimited_weekly' => 'UNLIMITED-WEEKLY',
            'unlimited_monthly' => 'UNLIMITED-MONTHLY',
            'daily' => 'DAILY',
            'weekly' => 'WEEKLY',
            'monthly' => 'MONTHLY',
            default => 'DEFAULT'
        };
    }

    private function getDataLimit(string $package): ?int
    {
        return match($package) {
            'daily_1gb' => 1024,                    // 1GB in MB
            'weekly_5gb' => 5 * 1024,              // 5GB in MB
            'monthly_20gb' => 20 * 1024,           // 20GB in MB
            'daily' => null,                       // No data limit for unlimited packages
            'weekly' => null,
            'monthly' => null,
            'unlimited_daily' => null,
            'unlimited_weekly' => null,
            'unlimited_monthly' => null,
            default => null
        };
    }

    public function disableVoucher(string $voucherCode): bool
    {
        try {
            $voucher = VoucherModel::where('code', $voucherCode)->firstOrFail();

            // Disable on MikroTik
            $disabled = $this->mikrotik->disableVoucher($voucherCode);

            if ($disabled) {
                // Update voucher status in database
                $voucher->disable();

                Log::channel('voucher')->info('Voucher disabled successfully', [
                    'voucher_id' => $voucher->id,
                    'code' => $voucherCode
                ]);
            }

            return $disabled;

        } catch (\Exception $e) {
            Log::channel('voucher')->error('Failed to disable voucher', [
                'voucher_code' => $voucherCode,
                'error' => $e->getMessage()
            ]);

            return false;
        }
    }

    public function getVoucherUsage(string $voucherCode): array
    {
        try {
            $voucher = VoucherModel::where('code', $voucherCode)->firstOrFail();

            // Get active connections from MikroTik
            $connections = $this->mikrotik->getUserConnections($voucherCode);

            // Calculate data usage
            $totalBytes = 0;
            foreach ($connections as $connection) {
                $totalBytes += ($connection['bytes_in'] + $connection['bytes_out']);
            }

            $dataUsageGB = round($totalBytes / (1024 * 1024 * 1024), 2);
            $dataUsagePercentage = null;

            if ($voucher->data_limit_mb) {
                $limitBytes = $voucher->data_limit_mb * 1024 * 1024;
                $dataUsagePercentage = min(100, ($totalBytes / $limitBytes) * 100);
            }

            return [
                'voucher' => [
                    'id' => $voucher->id,
                    'code' => $voucher->code,
                    'profile' => $voucher->profile,
                    'validity_hours' => $voucher->validity_hours,
                    'data_limit_mb' => $voucher->data_limit_mb,
                    'data_limit_formatted' => $voucher->getDataLimitFormattedAttribute(),
                    'status' => $voucher->status,
                    'activated_at' => $voucher->activated_at,
                    'expires_at' => $voucher->expires_at,
                    'remaining_hours' => now()->diffInHours($voucher->expires_at, false),
                    'remaining_time_formatted' => $voucher->getRemainingTimeAttribute(),
                ],
                'usage' => [
                    'active_connections' => count($connections),
                    'connections' => $connections,
                    'total_data_used_bytes' => $totalBytes,
                    'total_data_used_formatted' => $this->formatBytes($totalBytes),
                    'data_usage_gb' => $dataUsageGB,
                    'data_usage_percentage' => $dataUsagePercentage,
                    'is_expired' => $voucher->expires_at->isPast(),
                    'is_active' => $voucher->getIsActiveAttribute(),
                ],
                'customer' => $voucher->customer ? [
                    'id' => $voucher->customer->id,
                    'name' => $voucher->customer->name,
                    'phone' => $voucher->customer->phone,
                    'email' => $voucher->customer->email,
                ] : null,
                'payment' => $voucher->payment ? [
                    'id' => $voucher->payment->id,
                    'transaction_id' => $voucher->payment->transaction_id,
                    'amount' => $voucher->payment->amount,
                    'currency' => $voucher->payment->currency,
                    'paid_at' => $voucher->payment->paid_at,
                ] : null,
            ];

        } catch (\Exception $e) {
            Log::channel('voucher')->error('Failed to get voucher usage', [
                'voucher_code' => $voucherCode,
                'error' => $e->getMessage()
            ]);

            return [
                'error' => $e->getMessage(),
                'voucher_code' => $voucherCode
            ];
        }
    }

    public function renewVoucher(string $voucherCode, int $additionalHours): array
    {
        DB::beginTransaction();

        try {
            $voucher = VoucherModel::where('code', $voucherCode)->firstOrFail();

            if (!$voucher->getIsActiveAttribute()) {
                throw new \Exception('Cannot renew inactive or expired voucher');
            }

            // Calculate new expiry
            $newExpiry = $voucher->expires_at->addHours($additionalHours);

            // Update voucher in database
            $voucher->renew($additionalHours);

            // Update voucher on MikroTik
            // Note: MikroTik doesn't directly support extending validity
            // We need to disable the old one and create a new one with extended time
            // or update the limit-uptime if using user profile

            // For now, we'll just update the database and log
            Log::channel('voucher')->info('Voucher renewed in database', [
                'voucher_id' => $voucher->id,
                'code' => $voucherCode,
                'additional_hours' => $additionalHours,
                'new_expiry' => $newExpiry
            ]);

            DB::commit();

            return [
                'success' => true,
                'message' => 'Voucher renewed successfully',
                'voucher' => [
                    'code' => $voucher->code,
                    'new_expires_at' => $newExpiry->toISOString(),
                    'total_validity_hours' => $voucher->validity_hours,
                    'remaining_hours' => now()->diffInHours($newExpiry, false)
                ]
            ];

        } catch (\Exception $e) {
            DB::rollBack();

            Log::channel('voucher')->error('Failed to renew voucher', [
                'voucher_code' => $voucherCode,
                'additional_hours' => $additionalHours,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => 'Failed to renew voucher: ' . $e->getMessage(),
                'error' => $e->getMessage()
            ];
        }
    }

    public function syncWithRouter(string $voucherCode): array
    {
        try {
            $voucher = VoucherModel::where('code', $voucherCode)->firstOrFail();

            // Check if voucher exists on router
            $routerUser = $this->mikrotik->getUser($voucherCode);

            if (!$routerUser) {
                // Voucher missing on router, recreate it
                $voucherDTO = VoucherDTO::create(
                    code: $voucher->code,
                    password: $voucher->password,
                    profile: $voucher->profile,
                    validityHours: $voucher->validity_hours,
                    dataLimitMB: $voucher->data_limit_mb,
                    price: $voucher->price,
                    currency: $voucher->currency,
                    customerName: $voucher->customer->name,
                    customerPhone: $voucher->customer->phone,
                    customerEmail: $voucher->customer->email,
                    createdAt: $voucher->created_at,
                    expiresAt: $voucher->expires_at,
                    metadata: $voucher->metadata
                );

                $created = $this->mikrotik->createVoucher($voucherDTO);

                return [
                    'success' => $created,
                    'action' => 'created',
                    'message' => $created ? 'Voucher recreated on router' : 'Failed to recreate voucher on router',
                    'voucher_code' => $voucherCode
                ];
            }

            // Voucher exists on router, check if it's active
            $isDisabled = $routerUser['disabled'] ?? false;
            $isActiveOnRouter = !$isDisabled;
            $isActiveInDb = $voucher->getIsActiveAttribute();

            if ($isActiveOnRouter !== $isActiveInDb) {
                // Status mismatch, sync it
                if ($isActiveInDb) {
                    // Enable on router
                    // Note: MikroTik doesn't have a direct enable command for disabled users
                    // We might need to update the user or recreate it
                    return [
                        'success' => false,
                        'action' => 'status_mismatch',
                        'message' => 'Voucher status mismatch between database and router',
                        'router_status' => $isActiveOnRouter ? 'active' : 'disabled',
                        'database_status' => $voucher->status,
                        'voucher_code' => $voucherCode
                    ];
                } else {
                    // Disable on router
                    $disabled = $this->mikrotik->disableVoucher($voucherCode);

                    return [
                        'success' => $disabled,
                        'action' => 'disabled',
                        'message' => $disabled ? 'Voucher disabled on router' : 'Failed to disable voucher on router',
                        'voucher_code' => $voucherCode
                    ];
                }
            }

            return [
                'success' => true,
                'action' => 'synced',
                'message' => 'Voucher is already in sync with router',
                'voucher_code' => $voucherCode,
                'status' => $isActiveOnRouter ? 'active' : 'disabled'
            ];

        } catch (\Exception $e) {
            Log::channel('voucher')->error('Failed to sync voucher with router', [
                'voucher_code' => $voucherCode,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'action' => 'error',
                'message' => 'Failed to sync voucher: ' . $e->getMessage(),
                'error' => $e->getMessage()
            ];
        }
    }

    public function getVoucherStatistics(): array
    {
        try {
            $totalVouchers = VoucherModel::count();
            $activeVouchers = VoucherModel::active()->count();
            $expiredVouchers = VoucherModel::expired()->count();
            $usedVouchers = VoucherModel::used()->count();
            $disabledVouchers = VoucherModel::disabled()->count();

            // Get today's vouchers
            $todayVouchers = VoucherModel::whereDate('created_at', today())->count();

            // Get revenue from vouchers
            $totalRevenue = VoucherModel::where('status', 'active')
                ->orWhere('status', 'used')
                ->sum('price') ?? 0;

            // Get average voucher price
            $averagePrice = $totalVouchers > 0 ? round($totalRevenue / $totalVouchers, 2) : 0;

            // Get popular profiles
            $popularProfiles = VoucherModel::select('profile', DB::raw('count(*) as count'))
                ->groupBy('profile')
                ->orderByDesc('count')
                ->limit(5)
                ->get()
                ->toArray();

            return [
                'total_vouchers' => $totalVouchers,
                'active_vouchers' => $activeVouchers,
                'expired_vouchers' => $expiredVouchers,
                'used_vouchers' => $usedVouchers,
                'disabled_vouchers' => $disabledVouchers,
                'today_vouchers' => $todayVouchers,
                'total_revenue' => $totalRevenue,
                'average_price' => $averagePrice,
                'popular_profiles' => $popularProfiles,
                'active_percentage' => $totalVouchers > 0 ? round(($activeVouchers / $totalVouchers) * 100, 2) : 0,
            ];

        } catch (\Exception $e) {
            Log::channel('voucher')->error('Failed to get voucher statistics', [
                'error' => $e->getMessage()
            ]);

            return [
                'total_vouchers' => 0,
                'active_vouchers' => 0,
                'expired_vouchers' => 0,
                'used_vouchers' => 0,
                'disabled_vouchers' => 0,
                'today_vouchers' => 0,
                'total_revenue' => 0,
                'average_price' => 0,
                'popular_profiles' => [],
                'active_percentage' => 0,
                'error' => $e->getMessage()
            ];
        }
    }

    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $index = 0;

        while ($bytes >= 1024 && $index < count($units) - 1) {
            $bytes /= 1024;
            $index++;
        }

        return round($bytes, 2) . ' ' . $units[$index];
    }

    public function batchGenerateVouchers(array $data): array
    {
        DB::beginTransaction();

        try {
            $results = [
                'total' => 0,
                'successful' => 0,
                'failed' => 0,
                'vouchers' => [],
                'errors' => []
            ];

            foreach ($data as $item) {
                try {
                    // Validate required fields
                    if (!isset($item['quantity']) || !isset($item['profile']) || !isset($item['validity_hours'])) {
                        throw new \Exception('Missing required fields: quantity, profile, or validity_hours');
                    }

                    $quantity = (int) $item['quantity'];
                    $profile = $item['profile'];
                    $validityHours = (int) $item['validity_hours'];
                    $price = $item['price'] ?? null;
                    $dataLimitMB = $item['data_limit_mb'] ?? $this->getDataLimit($profile);

                    for ($i = 0; $i < $quantity; $i++) {
                        $code = $this->generateUniqueCode();
                        $password = Str::random(8);

                        // Create voucher DTO
                        $voucherDTO = VoucherDTO::create(
                            code: $code,
                            password: $password,
                            profile: $profile,
                            validityHours: $validityHours,
                            dataLimitMB: $dataLimitMB,
                            price: $price,
                            metadata: [
                                'batch_generated' => true,
                                'batch_data' => $item
                            ]
                        );

                        // Create on MikroTik
                        $created = $this->mikrotik->createVoucher($voucherDTO);

                        if ($created) {
                            // Create in database
                            $voucher = VoucherModel::create([
                                'uuid' => Str::orderedUuid(),
                                'customer_id' => null, // No customer in batch generation
                                'payment_id' => null,  // No payment in batch generation
                                'code' => $code,
                                'password' => $password,
                                'profile' => $profile,
                                'validity_hours' => $validityHours,
                                'data_limit_mb' => $dataLimitMB,
                                'price' => $price,
                                'currency' => 'UGX',
                                'status' => 'active',
                                'activated_at' => now(),
                                'expires_at' => now()->addHours($validityHours),
                                'metadata' => [
                                    'batch_generated' => true,
                                    'generated_at' => now()->toISOString(),
                                    'batch_data' => $item
                                ]
                            ]);

                            $results['successful']++;
                            $results['vouchers'][] = [
                                'code' => $code,
                                'password' => $password,
                                'profile' => $profile,
                                'validity_hours' => $validityHours,
                                'price' => $price
                            ];
                        } else {
                            $results['failed']++;
                            $results['errors'][] = "Failed to create voucher: {$code}";
                        }

                        $results['total']++;
                    }

                } catch (\Exception $e) {
                    $quantity = (int) ($item['quantity'] ?? 1);
                    $results['failed'] += $quantity;
                    $results['total'] += $quantity;
                    $results['errors'][] = "Batch item failed: " . $e->getMessage();
                }
            }

            DB::commit();

            Log::channel('voucher')->info('Batch voucher generation completed', $results);

            return $results;

        } catch (\Exception $e) {
            DB::rollBack();

            Log::channel('voucher')->error('Batch voucher generation failed', [
                'error' => $e->getMessage(),
                'data' => $data
            ]);

            return [
                'total' => 0,
                'successful' => 0,
                'failed' => 0,
                'vouchers' => [],
                'errors' => [$e->getMessage()],
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Advanced voucher generation with customizable parameters
     */
    public function generateAdvancedVoucher(array $parameters): VoucherModel
    {
        DB::beginTransaction();

        try {
            // Generate unique voucher code with custom prefix if provided
            $prefix = $parameters['code_prefix'] ?? 'BIL';
            $code = $this->generateUniqueCodeWithPrefix($prefix);
            $password = $parameters['password'] ?? Str::random(8);

            // Determine package details from parameters
            $profile = $parameters['profile'];
            $validityHours = $parameters['validity_hours'];
            $dataLimitMB = $parameters['data_limit_mb'] ?? null;
            $price = $parameters['price'] ?? 0;
            $currency = $parameters['currency'] ?? 'UGX';
            $customerId = $parameters['customer_id'] ?? null;
            $paymentId = $parameters['payment_id'] ?? null;

            // Create voucher DTO for router
            $voucherDTO = VoucherDTO::create(
                code: $code,
                password: $password,
                profile: $profile,
                validityHours: $validityHours,
                dataLimitMB: $dataLimitMB,
                price: $price,
                currency: $currency,
                customerName: $parameters['customer_name'] ?? null,
                customerPhone: $parameters['customer_phone'] ?? null,
                customerEmail: $parameters['customer_email'] ?? null,
                createdAt: now(),
                expiresAt: now()->addHours($validityHours),
                metadata: array_merge([
                    'advanced_generation' => true,
                    'generation_parameters' => $parameters
                ], $parameters['metadata'] ?? [])
            );

            // Create voucher on MikroTik FIRST
            $voucherCreated = $this->mikrotik->createVoucher($voucherDTO);

            if (!$voucherCreated) {
                throw new \Exception('Failed to create voucher on MikroTik router');
            }

            // Create voucher in database
            $voucher = VoucherModel::create([
                'uuid' => Str::orderedUuid(),
                'customer_id' => $customerId,
                'payment_id' => $paymentId,
                'code' => $code,
                'password' => $password,
                'profile' => $profile,
                'validity_hours' => $validityHours,
                'data_limit_mb' => $dataLimitMB,
                'price' => $price,
                'currency' => $currency,
                'status' => $parameters['auto_activate'] ?? true ? 'active' : 'pending',
                'activated_at' => $parameters['auto_activate'] ?? true ? now() : null,
                'expires_at' => $parameters['auto_activate'] ?? true ? now()->addHours($validityHours) : null,
                'router_metadata' => [
                    'created_on_router' => true,
                    'created_at' => now()->toISOString(),
                    'advanced_generation' => true
                ],
                'metadata' => array_merge([
                    'advanced_generation' => true,
                    'generated_at' => now()->toISOString(),
                    'generation_parameters' => $parameters
                ], $parameters['metadata'] ?? [])
            ]);

            // Send voucher via SMS if requested and customer phone is provided
            if (($parameters['send_sms'] ?? false) && !empty($parameters['customer_phone'])) {
                $smsSent = $this->smsService->sendVoucher($parameters['customer_phone'], $voucher);
                if ($smsSent) {
                    $voucher->markSmsSent();
                }
            }

            DB::commit();

            Log::channel('voucher')->info('Advanced voucher generated successfully', [
                'voucher_id' => $voucher->id,
                'code' => $code,
                'profile' => $profile,
                'validity_hours' => $validityHours,
                'parameters' => $parameters
            ]);

            return $voucher;

        } catch (\Exception $e) {
            DB::rollBack();

            Log::channel('voucher')->error('Advanced voucher generation failed', [
                'parameters' => $parameters,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            throw $e;
        }
    }

    /**
     * Transfer voucher between customers
     */
    public function transferVoucher(string $voucherCode, string $newCustomerId, ?string $reason = null): array
    {
        DB::beginTransaction();

        try {
            $voucher = VoucherModel::where('code', $voucherCode)->firstOrFail();
            $newCustomer = \App\Models\Customer::findOrFail($newCustomerId);
            $oldCustomer = $voucher->customer;

            // Validate transfer conditions
            if (!$voucher->isUsable()) {
                throw new \Exception('Cannot transfer inactive, expired, or used voucher');
            }

            // Store old customer info for audit
            $oldCustomerId = $voucher->customer_id;
            $oldCustomerName = $oldCustomer?->name;

            // Update voucher customer
            $voucher->update([
                'customer_id' => $newCustomerId,
                'metadata' => array_merge($voucher->metadata ?? [], [
                    'transfer_history' => array_merge(
                        $voucher->metadata['transfer_history'] ?? [],
                        [[
                            'from_customer_id' => $oldCustomerId,
                            'from_customer_name' => $oldCustomerName,
                            'to_customer_id' => $newCustomerId,
                            'to_customer_name' => $newCustomer->name,
                            'transferred_at' => now()->toISOString(),
                            'reason' => $reason,
                            'transferred_by' => auth()->id() ?? 'system'
                        ]]
                    )
                ])
            ]);

            // Send SMS notification to new customer if they have a phone
            if ($newCustomer->phone) {
                $smsSent = $this->smsService->sendVoucherTransfer($newCustomer->phone, $voucher, $oldCustomerName);
                if ($smsSent) {
                    $voucher->update(['sms_sent_at' => now()]);
                }
            }

            DB::commit();

            Log::channel('voucher')->info('Voucher transferred successfully', [
                'voucher_id' => $voucher->id,
                'code' => $voucherCode,
                'from_customer_id' => $oldCustomerId,
                'to_customer_id' => $newCustomerId,
                'reason' => $reason,
                'transferred_by' => auth()->id() ?? 'system'
            ]);

            return [
                'success' => true,
                'message' => 'Voucher transferred successfully',
                'voucher' => [
                    'code' => $voucher->code,
                    'old_customer' => $oldCustomerName,
                    'new_customer' => $newCustomer->name,
                    'transferred_at' => now()->toISOString()
                ]
            ];

        } catch (\Exception $e) {
            DB::rollBack();

            Log::channel('voucher')->error('Voucher transfer failed', [
                'voucher_code' => $voucherCode,
                'new_customer_id' => $newCustomerId,
                'reason' => $reason,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => 'Failed to transfer voucher: ' . $e->getMessage(),
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Refund and cancel voucher
     */
    public function refundVoucher(string $voucherCode, array $refundData): array
    {
        DB::beginTransaction();

        try {
            $voucher = VoucherModel::where('code', $voucherCode)->firstOrFail();

            // Validate refund conditions
            if ($voucher->status === 'used') {
                throw new \Exception('Cannot refund used voucher');
            }

            if ($voucher->status === 'expired' && !($refundData['allow_expired_refund'] ?? false)) {
                throw new \Exception('Cannot refund expired voucher');
            }

            // Calculate refund amount
            $refundAmount = $refundData['refund_amount'] ?? $voucher->price;
            $refundReason = $refundData['reason'] ?? 'Customer request';
            $refundMethod = $refundData['method'] ?? 'manual';

            // Disable voucher on MikroTik
            $this->mikrotik->disableVoucher($voucherCode);

            // Update voucher status
            $voucher->update([
                'status' => 'disabled',
                'metadata' => array_merge($voucher->metadata ?? [], [
                    'refund_info' => [
                        'refunded' => true,
                        'refund_amount' => $refundAmount,
                        'refund_reason' => $refundReason,
                        'refund_method' => $refundMethod,
                        'refunded_at' => now()->toISOString(),
                        'refunded_by' => auth()->id() ?? 'system',
                        'original_price' => $voucher->price
                    ]
                ])
            ]);

            // Create refund record if payment exists
            if ($voucher->payment_id && $refundMethod === 'automatic') {
                // Here you would integrate with payment gateway to process refund
                // For now, we'll just log the refund request
                Log::channel('voucher')->info('Refund requested for payment gateway', [
                    'voucher_id' => $voucher->id,
                    'payment_id' => $voucher->payment_id,
                    'refund_amount' => $refundAmount
                ]);
            }

            DB::commit();

            Log::channel('voucher')->info('Voucher refunded successfully', [
                'voucher_id' => $voucher->id,
                'code' => $voucherCode,
                'refund_amount' => $refundAmount,
                'refund_reason' => $refundReason,
                'refunded_by' => auth()->id() ?? 'system'
            ]);

            return [
                'success' => true,
                'message' => 'Voucher refunded successfully',
                'refund' => [
                    'voucher_code' => $voucherCode,
                    'refund_amount' => $refundAmount,
                    'refund_reason' => $refundReason,
                    'refunded_at' => now()->toISOString()
                ]
            ];

        } catch (\Exception $e) {
            DB::rollBack();

            Log::channel('voucher')->error('Voucher refund failed', [
                'voucher_code' => $voucherCode,
                'refund_data' => $refundData,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => 'Failed to refund voucher: ' . $e->getMessage(),
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Get comprehensive voucher analytics
     */
    public function getVoucherAnalytics(array $filters = []): array
    {
        try {
            $query = VoucherModel::with(['customer', 'payment']);

            // Apply date filters
            if (!empty($filters['start_date'])) {
                $query->whereDate('created_at', '>=', $filters['start_date']);
            }
            if (!empty($filters['end_date'])) {
                $query->whereDate('created_at', '<=', $filters['end_date']);
            }

            // Apply other filters
            if (!empty($filters['profile'])) {
                $query->where('profile', $filters['profile']);
            }
            if (!empty($filters['status'])) {
                $query->where('status', $filters['status']);
            }

            $vouchers = $query->get();

            // Calculate analytics
            $analytics = [
                'overview' => [
                    'total_vouchers' => $vouchers->count(),
                    'active_vouchers' => $vouchers->where('status', 'active')->count(),
                    'expired_vouchers' => $vouchers->where('status', 'expired')->count(),
                    'used_vouchers' => $vouchers->where('status', 'used')->count(),
                    'disabled_vouchers' => $vouchers->where('status', 'disabled')->count(),
                    'total_revenue' => $vouchers->sum('price'),
                    'average_price' => $vouchers->avg('price') ?? 0
                ],
                'profile_breakdown' => $vouchers->groupBy('profile')->map(function ($group, $profile) {
                    return [
                        'profile' => $profile,
                        'count' => $group->count(),
                        'revenue' => $group->sum('price'),
                        'active' => $group->where('status', 'active')->count(),
                        'expired' => $group->where('status', 'expired')->count()
                    ];
                })->values(),
                'daily_generation' => $vouchers->groupBy(function ($voucher) {
                    return $voucher->created_at->format('Y-m-d');
                })->map(function ($group, $date) {
                    return [
                        'date' => $date,
                        'count' => $group->count(),
                        'revenue' => $group->sum('price')
                    ];
                })->values(),
                'customer_insights' => [
                    'vouchers_with_customers' => $vouchers->whereNotNull('customer_id')->count(),
                    'vouchers_without_customers' => $vouchers->whereNull('customer_id')->count(),
                    'top_customers' => $vouchers->whereNotNull('customer_id')
                        ->groupBy('customer_id')
                        ->map(function ($group) {
                            $customer = $group->first()->customer;
                            return [
                                'customer_id' => $customer->id,
                                'customer_name' => $customer->name,
                                'voucher_count' => $group->count(),
                                'total_spent' => $group->sum('price')
                            ];
                        })
                        ->sortByDesc('voucher_count')
                        ->take(10)
                        ->values(),
                ],
                'usage_patterns' => [
                    'average_validity_hours' => $vouchers->avg('validity_hours') ?? 0,
                    'most_popular_validity' => $vouchers->groupBy('validity_hours')
                        ->map->count()
                        ->sortDesc()
                        ->keys()
                        ->first(),
                    'data_limit_distribution' => $vouchers->whereNotNull('data_limit_mb')
                        ->groupBy('data_limit_mb')
                        ->map->count()
                        ->sortDesc()
                ],
                'financial_metrics' => [
                    'total_revenue' => $vouchers->sum('price'),
                    'revenue_by_status' => $vouchers->groupBy('status')->map->sum('price'),
                    'average_revenue_per_voucher' => $vouchers->avg('price') ?? 0,
                    'refunded_amount' => $vouchers->filter(function ($voucher) {
                        return isset($voucher->metadata['refund_info']['refunded']) && 
                               $voucher->metadata['refund_info']['refunded'];
                    })->sum(function ($voucher) {
                        return $voucher->metadata['refund_info']['refund_amount'] ?? 0;
                    })
                ]
            ];

            return $analytics;

        } catch (\Exception $e) {
            Log::channel('voucher')->error('Failed to generate voucher analytics', [
                'filters' => $filters,
                'error' => $e->getMessage()
            ]);

            return [
                'error' => $e->getMessage(),
                'overview' => [
                    'total_vouchers' => 0,
                    'active_vouchers' => 0,
                    'expired_vouchers' => 0,
                    'used_vouchers' => 0,
                    'disabled_vouchers' => 0,
                    'total_revenue' => 0,
                    'average_price' => 0
                ]
            ];
        }
    }

    /**
     * Automatic voucher cleanup based on expiration policies
     */
    public function cleanupExpiredVouchers(array $policies = []): array
    {
        try {
            $defaultPolicies = [
                'auto_disable_after_days' => 30,
                'delete_after_days' => 90,
                'notify_before_cleanup' => true
            ];

            $policies = array_merge($defaultPolicies, $policies);
            
            $results = [
                'disabled' => 0,
                'deleted' => 0,
                'notified' => 0,
                'errors' => []
            ];

            // Find expired vouchers to disable
            $expiredVouchers = VoucherModel::where('status', 'expired')
                ->where('expires_at', '<', now()->subDays($policies['auto_disable_after_days']))
                ->get();

            foreach ($expiredVouchers as $voucher) {
                try {
                    $this->mikrotik->disableVoucher($voucher->code);
                    $voucher->update(['status' => 'disabled']);
                    $results['disabled']++;
                } catch (\Exception $e) {
                    $results['errors'][] = "Failed to disable voucher {$voucher->code}: " . $e->getMessage();
                }
            }

            // Find old disabled vouchers to delete
            if ($policies['delete_after_days'] > 0) {
                $oldVouchers = VoucherModel::where('status', 'disabled')
                    ->where('updated_at', '<', now()->subDays($policies['delete_after_days']))
                    ->get();

                foreach ($oldVouchers as $voucher) {
                    try {
                        $voucher->delete();
                        $results['deleted']++;
                    } catch (\Exception $e) {
                        $results['errors'][] = "Failed to delete voucher {$voucher->code}: " . $e->getMessage();
                    }
                }
            }

            Log::channel('voucher')->info('Voucher cleanup completed', $results);

            return $results;

        } catch (\Exception $e) {
            Log::channel('voucher')->error('Voucher cleanup failed', [
                'policies' => $policies,
                'error' => $e->getMessage()
            ]);

            return [
                'disabled' => 0,
                'deleted' => 0,
                'notified' => 0,
                'errors' => [$e->getMessage()]
            ];
        }
    }

    /**
     * Generate unique code with custom prefix
     */
    private function generateUniqueCodeWithPrefix(string $prefix): string
    {
        do {
            // Format: PREFIX-XXXX-XXXX where X is alphanumeric
            $code = strtoupper($prefix) . '-' . strtoupper(Str::random(4)) . '-' . strtoupper(Str::random(4));
        } while (VoucherModel::where('code', $code)->exists());

        return $code;
    }
}
