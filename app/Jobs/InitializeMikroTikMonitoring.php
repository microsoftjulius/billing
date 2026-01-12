<?php

namespace App\Jobs;

use App\Models\MikroTikDevice;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class InitializeMikroTikMonitoring implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public MikroTikDevice $device
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            Log::info('Initializing MikroTik monitoring for device', [
                'device_id' => $this->device->id,
                'device_name' => $this->device->name,
                'ip_address' => $this->device->ip_address,
            ]);

            // TODO: Implement actual MikroTik API connection and monitoring setup
            // This would typically involve:
            // 1. Testing initial connection to the device
            // 2. Setting up monitoring parameters
            // 3. Scheduling periodic status checks
            // 4. Initializing device configuration if needed

            // For now, we'll just log the initialization and set initial status
            $this->device->update([
                'status' => 'offline', // Will be updated by actual monitoring
                'last_seen' => null,
            ]);

            Log::info('MikroTik monitoring initialized successfully', [
                'device_id' => $this->device->id,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to initialize MikroTik monitoring', [
                'device_id' => $this->device->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Update device status to error
            $this->device->update(['status' => 'error']);

            // Re-throw the exception to mark the job as failed
            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('InitializeMikroTikMonitoring job failed', [
            'device_id' => $this->device->id,
            'error' => $exception->getMessage(),
        ]);

        // Update device status to error
        $this->device->update(['status' => 'error']);
    }
}