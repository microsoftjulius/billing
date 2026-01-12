<?php

namespace App\Console\Commands;

use App\Models\MikroTikDevice;
use App\Services\MikroTikApiService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class MonitorMikroTikDevices extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'mikrotik:monitor {--device-id= : Monitor specific device ID}';

    /**
     * The console command description.
     */
    protected $description = 'Monitor MikroTik device status and update database';

    private MikroTikApiService $apiService;

    public function __construct(MikroTikApiService $apiService)
    {
        parent::__construct();
        $this->apiService = $apiService;
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $deviceId = $this->option('device-id');
        
        if ($deviceId) {
            $this->monitorSingleDevice($deviceId);
        } else {
            $this->monitorAllDevices();
        }

        return 0;
    }

    /**
     * Monitor a single device
     */
    private function monitorSingleDevice(string $deviceId): void
    {
        try {
            $device = MikroTikDevice::findOrFail($deviceId);
            $this->info("Monitoring device: {$device->name} ({$device->ip_address})");
            
            $status = $this->apiService->monitorDeviceStatus($device);
            
            $this->info("Device status: {$status['status']}");
            
            if ($status['status'] === 'offline') {
                $this->warn("Device is offline: {$status['error'] ?? 'Unknown error'}");
            }
            
        } catch (\Exception $e) {
            $this->error("Failed to monitor device {$deviceId}: {$e->getMessage()}");
            Log::error('Device monitoring failed', [
                'device_id' => $deviceId,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Monitor all devices
     */
    private function monitorAllDevices(): void
    {
        $devices = MikroTikDevice::all();
        $totalDevices = $devices->count();
        
        if ($totalDevices === 0) {
            $this->info('No devices to monitor');
            return;
        }

        $this->info("Monitoring {$totalDevices} devices...");
        
        $onlineCount = 0;
        $offlineCount = 0;
        $errorCount = 0;

        $progressBar = $this->output->createProgressBar($totalDevices);
        $progressBar->start();

        foreach ($devices as $device) {
            try {
                $status = $this->apiService->monitorDeviceStatus($device);
                
                if ($status['status'] === 'online') {
                    $onlineCount++;
                } else {
                    $offlineCount++;
                }
                
            } catch (\Exception $e) {
                $errorCount++;
                Log::error('Device monitoring failed', [
                    'device_id' => $device->id,
                    'device_name' => $device->name,
                    'error' => $e->getMessage()
                ]);
            }
            
            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine();

        $this->info("Monitoring complete:");
        $this->info("  Online: {$onlineCount}");
        $this->info("  Offline: {$offlineCount}");
        
        if ($errorCount > 0) {
            $this->warn("  Errors: {$errorCount}");
        }
    }
}