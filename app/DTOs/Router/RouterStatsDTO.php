<?php

namespace App\DTOs\Router;

use Carbon\Carbon;

readonly class RouterStatsDTO
{
    public function __construct(
        public float   $cpuLoad,
        public float   $memoryUsage,
        public float   $diskUsage,
        public int     $uptimeSeconds,
        public int     $totalUsers,
        public int     $activeUsers,
        public int     $totalVouchers,
        public int     $activeVouchers,
        public float   $totalBandwidthUsedGB,
        public float   $averageUploadSpeedMbps,
        public float   $averageDownloadSpeedMbps,
        public int     $totalHotspotProfiles,
        public int     $totalQueues,
        public int     $totalFirewallRules,
        public Carbon  $lastReboot,
        public ?string $firmwareVersion,
        public ?string $model,
        public array   $interfaces,
        public array   $services,
        public array   $metadata
    ) {}

    public static function fromMikrotikData(array $data): self
    {
        $cpuLoad = isset($data['cpu-load']) ? (float) $data['cpu-load'] : 0.0;

        // Calculate memory usage percentage
        $freeMemory = isset($data['free-memory']) ? (float) $data['free-memory'] : 0;
        $totalMemory = isset($data['total-memory']) ? (float) $data['total-memory'] : 1;
        $memoryUsage = $totalMemory > 0 ? 100 - (($freeMemory / $totalMemory) * 100) : 0.0;

        // Calculate disk usage percentage
        $freeHddSpace = isset($data['free-hdd-space']) ? (float) $data['free-hdd-space'] : 0;
        $totalHddSpace = isset($data['total-hdd-space']) ? (float) $data['total-hdd-space'] : 1;
        $diskUsage = $totalHddSpace > 0 ? 100 - (($freeHddSpace / $totalHddSpace) * 100) : 0.0;

        // Parse uptime
        $uptime = isset($data['uptime']) ? self::parseMikrotikUptime($data['uptime']) : 0;

        // Calculate last reboot time
        $lastReboot = Carbon::now()->subSeconds($uptime);

        // Parse firmware version and model
        $firmwareVersion = $data['version'] ?? null;
        $model = $data['board-name'] ?? $data['model'] ?? null;

        return new self(
            cpuLoad: $cpuLoad,
            memoryUsage: $memoryUsage,
            diskUsage: $diskUsage,
            uptimeSeconds: $uptime,
            totalUsers: $data['total-users'] ?? 0,
            activeUsers: $data['active-users'] ?? 0,
            totalVouchers: $data['total-vouchers'] ?? 0,
            activeVouchers: $data['active-vouchers'] ?? 0,
            totalBandwidthUsedGB: isset($data['total-bandwidth-bytes'])
                ? (float) ($data['total-bandwidth-bytes'] / (1024 * 1024 * 1024))
                : 0.0,
            averageUploadSpeedMbps: isset($data['avg-upload-speed'])
                ? (float) ($data['avg-upload-speed'] / 1000000)
                : 0.0,
            averageDownloadSpeedMbps: isset($data['avg-download-speed'])
                ? (float) ($data['avg-download-speed'] / 1000000)
                : 0.0,
            totalHotspotProfiles: $data['hotspot-profiles'] ?? 0,
            totalQueues: $data['total-queues'] ?? 0,
            totalFirewallRules: $data['firewall-rules'] ?? 0,
            lastReboot: $lastReboot,
            firmwareVersion: $firmwareVersion,
            model: $model,
            interfaces: $data['interfaces'] ?? [],
            services: $data['services'] ?? [],
            metadata: $data
        );
    }

    public function toArray(): array
    {
        return [
            'cpu_load' => $this->cpuLoad,
            'memory_usage' => $this->memoryUsage,
            'disk_usage' => $this->diskUsage,
            'uptime_seconds' => $this->uptimeSeconds,
            'uptime_formatted' => $this->formatUptime(),
            'total_users' => $this->totalUsers,
            'active_users' => $this->activeUsers,
            'total_vouchers' => $this->totalVouchers,
            'active_vouchers' => $this->activeVouchers,
            'total_bandwidth_used_gb' => $this->totalBandwidthUsedGB,
            'average_upload_speed_mbps' => $this->averageUploadSpeedMbps,
            'average_download_speed_mbps' => $this->averageDownloadSpeedMbps,
            'total_hotspot_profiles' => $this->totalHotspotProfiles,
            'total_queues' => $this->totalQueues,
            'total_firewall_rules' => $this->totalFirewallRules,
            'last_reboot' => $this->lastReboot->toISOString(),
            'firmware_version' => $this->firmwareVersion,
            'model' => $this->model,
            'interfaces' => $this->interfaces,
            'services' => $this->services,
            'metadata' => $this->metadata,
            'is_healthy' => $this->isHealthy(),
            'health_status' => $this->getHealthStatus(),
            'load_average' => $this->calculateLoadAverage(),
        ];
    }

    private static function parseMikrotikUptime(string $uptime): int
    {
        // Parse MikroTik uptime format: 1d2h3m4s or 01:02:03
        $seconds = 0;

        if (str_contains($uptime, 'd') || str_contains($uptime, 'h') || str_contains($uptime, 'm') || str_contains($uptime, 's')) {
            preg_match_all('/(\d+)([dhms])/', $uptime, $matches, PREG_SET_ORDER);

            foreach ($matches as $match) {
                $value = (int) $match[1];
                $unit = $match[2];

                switch ($unit) {
                    case 'd': $seconds += $value * 86400; break;
                    case 'h': $seconds += $value * 3600; break;
                    case 'm': $seconds += $value * 60; break;
                    case 's': $seconds += $value; break;
                }
            }
        } elseif (str_contains($uptime, ':')) {
            $parts = explode(':', $uptime);
            $hours = (int) ($parts[0] ?? 0);
            $minutes = (int) ($parts[1] ?? 0);
            $secs = (int) ($parts[2] ?? 0);

            $seconds = ($hours * 3600) + ($minutes * 60) + $secs;
        }

        return $seconds;
    }

    private function formatUptime(): string
    {
        $days = floor($this->uptimeSeconds / 86400);
        $hours = floor(($this->uptimeSeconds % 86400) / 3600);
        $minutes = floor(($this->uptimeSeconds % 3600) / 60);

        if ($days > 0) {
            return sprintf('%d days, %d hours, %d minutes', $days, $hours, $minutes);
        }

        if ($hours > 0) {
            return sprintf('%d hours, %d minutes', $hours, $minutes);
        }

        return sprintf('%d minutes', $minutes);
    }

    public function isHealthy(): bool
    {
        return $this->cpuLoad < 80 &&
            $this->memoryUsage < 85 &&
            $this->diskUsage < 90 &&
            $this->uptimeSeconds > 3600; // At least 1 hour uptime
    }

    public function getHealthStatus(): string
    {
        if (!$this->isHealthy()) {
            return 'unhealthy';
        }

        if ($this->cpuLoad > 60 || $this->memoryUsage > 75) {
            return 'warning';
        }

        return 'healthy';
    }

    public function calculateLoadAverage(): array
    {
        // Simulate load average calculation (1min, 5min, 15min)
        $baseLoad = $this->cpuLoad / 100;

        return [
            '1min' => round($baseLoad * 0.8, 2),
            '5min' => round($baseLoad * 0.9, 2),
            '15min' => round($baseLoad, 2),
        ];
    }

    public function getMemoryUsageFormatted(): string
    {
        return round($this->memoryUsage, 1) . '%';
    }

    public function getCpuLoadFormatted(): string
    {
        return round($this->cpuLoad, 1) . '%';
    }

    public function getDiskUsageFormatted(): string
    {
        return round($this->diskUsage, 1) . '%';
    }

    public function getTotalBandwidthFormatted(): string
    {
        return round($this->totalBandwidthUsedGB, 2) . ' GB';
    }

    public function getUserActivityPercentage(): float
    {
        if ($this->totalUsers === 0) {
            return 0.0;
        }

        return ($this->activeUsers / $this->totalUsers) * 100;
    }
}
