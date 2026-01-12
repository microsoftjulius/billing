<?php

namespace Tests\Feature;

use App\Models\Voucher;
use App\Models\MikroTikUser;
use App\Models\MikroTikDevice;
use App\Models\Customer;
use App\Models\Payment;
use App\Services\VoucherService;
use App\Services\MikroTikApiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use Carbon\Carbon;

class VoucherSystemIntegrationTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected VoucherService $voucherService;
    protected MikroTikApiService $mikrotikService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->voucherService = app(VoucherService::class);
        $this->mikrotikService = app(MikroTikApiService::class);
    }

    /** @test */
    public function it_integrates_voucher_activation_with_mikrotik_user_creation()
    {
        // Arrange
        $device = MikroTikDevice::factory()->create([
            'status' => 'online',
            'ip_address' => '192.168.1.1'
        ]);
        
        $customer = Customer::factory()->create();
        
        $voucher = Voucher::factory()->create([
            'customer_id' => $customer->id,
            'status' => 'active',
            'duration_hours' => 24,
            'bandwidth_limit' => '10M/10M'
        ]);

        // Act
        $result = $this->voucherService->activateVoucherOnMikroTik($voucher, $device);

        // Assert
        $this->assertTrue($result);
        
        $mikrotikUser = MikroTikUser::where('voucher_id', $voucher->id)->first();
        $this->assertNotNull($mikrotikUser);
        $this->assertEquals($voucher->code, $mikrotikUser->username);
        $this->assertEquals($device->id, $mikrotikUser->mikrotik_device_id);
        $this->assertEquals('active', $mikrotikUser->status);
    }

    /** @test */
    public function it_handles_voucher_transfer_between_customers()
    {
        // Arrange
        $sourceCustomer = Customer::factory()->create();
        $targetCustomer = Customer::factory()->create();
        
        $voucher = Voucher::factory()->create([
            'customer_id' => $sourceCustomer->id,
            'status' => 'active'
        ]);

        // Act
        $result = $this->voucherService->transferVoucher($voucher, $targetCustomer);

        // Assert
        $this->assertTrue($result);
        
        $voucher->refresh();
        $this->assertEquals($targetCustomer->id, $voucher->customer_id);
        $this->assertEquals('transferred', $voucher->status);
        
        // Verify audit trail
        $this->assertDatabaseHas('voucher_transfers', [
            'voucher_id' => $voucher->id,
            'from_customer_id' => $sourceCustomer->id,
            'to_customer_id' => $targetCustomer->id
        ]);
    }

    /** @test */
    public function it_processes_voucher_refunds_with_payment_reversal()
    {
        // Arrange
        $customer = Customer::factory()->create();
        $payment = Payment::factory()->create([
            'customer_id' => $customer->id,
            'amount' => 5000,
            'status' => 'completed'
        ]);
        
        $voucher = Voucher::factory()->create([
            'customer_id' => $customer->id,
            'payment_id' => $payment->id,
            'status' => 'active',
            'amount' => 5000
        ]);

        // Act
        $result = $this->voucherService->refundVoucher($voucher, 'Customer request');

        // Assert
        $this->assertTrue($result);
        
        $voucher->refresh();
        $this->assertEquals('refunded', $voucher->status);
        
        // Verify refund record
        $this->assertDatabaseHas('voucher_refunds', [
            'voucher_id' => $voucher->id,
            'amount' => 5000,
            'reason' => 'Customer request'
        ]);
    }

    /** @test */
    public function it_handles_voucher_expiration_cleanup()
    {
        // Arrange
        $expiredVoucher = Voucher::factory()->create([
            'status' => 'active',
            'expires_at' => Carbon::now()->subDays(1)
        ]);
        
        $activeVoucher = Voucher::factory()->create([
            'status' => 'active',
            'expires_at' => Carbon::now()->addDays(1)
        ]);

        // Act
        $result = $this->voucherService->cleanupExpiredVouchers();

        // Assert
        $this->assertGreaterThan(0, $result);
        
        $expiredVoucher->refresh();
        $this->assertEquals('expired', $expiredVoucher->status);
        
        $activeVoucher->refresh();
        $this->assertEquals('active', $activeVoucher->status);
    }

    /** @test */
    public function it_tracks_voucher_usage_analytics()
    {
        // Arrange
        $customer = Customer::factory()->create();
        $device = MikroTikDevice::factory()->create();
        
        $vouchers = Voucher::factory()->count(5)->create([
            'customer_id' => $customer->id,
            'status' => 'used'
        ]);

        foreach ($vouchers as $voucher) {
            MikroTikUser::factory()->create([
                'voucher_id' => $voucher->id,
                'mikrotik_device_id' => $device->id,
                'bytes_in' => rand(1000000, 10000000),
                'bytes_out' => rand(1000000, 10000000)
            ]);
        }

        // Act
        $analytics = $this->voucherService->getVoucherAnalytics($customer);

        // Assert
        $this->assertArrayHasKey('total_vouchers', $analytics);
        $this->assertArrayHasKey('total_usage', $analytics);
        $this->assertArrayHasKey('average_session_duration', $analytics);
        $this->assertEquals(5, $analytics['total_vouchers']);
        $this->assertGreaterThan(0, $analytics['total_usage']);
    }

    /** @test */
    public function it_synchronizes_voucher_status_with_mikrotik_users()
    {
        // Arrange
        $device = MikroTikDevice::factory()->create(['status' => 'online']);
        $voucher = Voucher::factory()->create(['status' => 'active']);
        
        $mikrotikUser = MikroTikUser::factory()->create([
            'voucher_id' => $voucher->id,
            'mikrotik_device_id' => $device->id,
            'status' => 'active'
        ]);

        // Act - Simulate user disconnection
        $mikrotikUser->update(['status' => 'disconnected']);
        $this->voucherService->syncVoucherStatus($voucher);

        // Assert
        $voucher->refresh();
        $this->assertEquals('used', $voucher->status);
    }

    /** @test */
    public function it_handles_bulk_voucher_operations_with_progress_tracking()
    {
        // Arrange
        $customer = Customer::factory()->create();
        $voucherData = [
            'count' => 10,
            'duration_hours' => 24,
            'bandwidth_limit' => '5M/5M',
            'amount' => 2000
        ];

        // Act
        $result = $this->voucherService->generateBulkVouchers($customer, $voucherData);

        // Assert
        $this->assertTrue($result['success']);
        $this->assertEquals(10, $result['generated_count']);
        
        $vouchers = Voucher::where('customer_id', $customer->id)->get();
        $this->assertCount(10, $vouchers);
        
        foreach ($vouchers as $voucher) {
            $this->assertEquals(24, $voucher->duration_hours);
            $this->assertEquals('5M/5M', $voucher->bandwidth_limit);
            $this->assertEquals(2000, $voucher->amount);
        }
    }

    /** @test */
    public function it_validates_voucher_mikrotik_integration_constraints()
    {
        // Arrange
        $device = MikroTikDevice::factory()->create(['status' => 'offline']);
        $voucher = Voucher::factory()->create(['status' => 'active']);

        // Act & Assert - Should fail for offline device
        $result = $this->voucherService->activateVoucherOnMikroTik($voucher, $device);
        $this->assertFalse($result);
        
        // Arrange - Online device
        $device->update(['status' => 'online']);
        
        // Act & Assert - Should succeed for online device
        $result = $this->voucherService->activateVoucherOnMikroTik($voucher, $device);
        $this->assertTrue($result);
    }
}