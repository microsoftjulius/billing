<?php

namespace App\DTOs\Router;

use Carbon\Carbon;
use Illuminate\Support\Str;

readonly class UserConnectionDTO
{
    public function __construct(
        public string  $username,
        public string  $ipAddress,
        public string  $macAddress,
        public string  $interface,
        public ?Carbon $connectedSince,
        public ?Carbon $lastActive,
        public int     $uptimeSeconds,
        public int     $bytesIn,
        public int     $bytesOut,
        public int     $packetsIn,
        public int     $packetsOut,
        public float   $uploadSpeedBps,
        public float   $downloadSpeedBps,
        public ?string $hostname,
        public ?string $deviceType,
        public string  $connectionType,
        public string  $status,
        public string  $server,
        public array   $metadata
    ) {}

    public static function fromMikrotikData(array $data): self
    {
        // Parse MikroTik response data
        $bytesIn = isset($data['bytes-in']) ? (int) $data['bytes-in'] : 0;
        $bytesOut = isset($data['bytes-out']) ? (int) $data['bytes-out'] : 0;
        $uptime = isset($data['uptime']) ? self::parseMikrotikUptime($data['uptime']) : 0;

        // Parse speed limits
        $uploadSpeed = 0.0;
        $downloadSpeed = 0.0;
        if (isset($data['rate-limit'])) {
            $uploadSpeed = self::parseSpeed($data['rate-limit'], 'upload');
            $downloadSpeed = self::parseSpeed($data['rate-limit'], 'download');
        }

        // Parse timestamps
        $connectedSince = isset($data['login-time']) ? Carbon::parse($data['login-time']) : null;
        $lastActive = isset($data['last-logged-out']) ? Carbon::parse($data['last-logged-out']) : Carbon::now();

        // If connectedSince is null but we have uptime, calculate it
        if (!$connectedSince && $uptime > 0) {
            $connectedSince = Carbon::now()->subSeconds($uptime);
        }

        return new self(
            username: $data['user'] ?? $data['name'] ?? 'unknown',
            ipAddress: $data['address'] ?? $data['ip-address'] ?? '0.0.0.0',
            macAddress: $data['mac-address'] ?? $data['mac'] ?? '00:00:00:00:00:00',
            interface: $data['interface'] ?? 'unknown',
            connectedSince: $connectedSince,
            lastActive: $lastActive,
            uptimeSeconds: $uptime,
            bytesIn: $bytesIn,
            bytesOut: $bytesOut,
            packetsIn: isset($data['packets-in']) ? (int) $data['packets-in'] : 0,
            packetsOut: isset($data['packets-out']) ? (int) $data['packets-out'] : 0,
            uploadSpeedBps: $uploadSpeed,
            downloadSpeedBps: $downloadSpeed,
            hostname: $data['hostname'] ?? null,
            deviceType: self::guessDeviceType($data['user-agent'] ?? ''),
            connectionType: $data['type'] ?? 'hotspot',
            status: $data['status'] ?? 'active',
            server: $data['server'] ?? 'hotspot1',
            metadata: $data
        );
    }

    public static function fromArray(array $data): self
    {
        return new self(
            username: $data['username'] ?? $data['user'] ?? 'unknown',
            ipAddress: $data['ip_address'] ?? $data['ipAddress'] ?? '0.0.0.0',
            macAddress: $data['mac_address'] ?? $data['macAddress'] ?? '00:00:00:00:00:00',
            interface: $data['interface'] ?? 'unknown',
            connectedSince: isset($data['connected_since']) ? Carbon::parse($data['connected_since']) : null,
            lastActive: isset($data['last_active']) ? Carbon::parse($data['last_active']) : null,
            uptimeSeconds: $data['uptime_seconds'] ?? $data['uptimeSeconds'] ?? 0,
            bytesIn: $data['bytes_in'] ?? $data['bytesIn'] ?? 0,
            bytesOut: $data['bytes_out'] ?? $data['bytesOut'] ?? 0,
            packetsIn: $data['packets_in'] ?? $data['packetsIn'] ?? 0,
            packetsOut: $data['packets_out'] ?? $data['packetsOut'] ?? 0,
            uploadSpeedBps: $data['upload_speed_bps'] ?? $data['uploadSpeedBps'] ?? 0.0,
            downloadSpeedBps: $data['download_speed_bps'] ?? $data['downloadSpeedBps'] ?? 0.0,
            hostname: $data['hostname'] ?? null,
            deviceType: $data['device_type'] ?? $data['deviceType'] ?? null,
            connectionType: $data['connection_type'] ?? $data['connectionType'] ?? 'hotspot',
            status: $data['status'] ?? 'active',
            server: $data['server'] ?? 'hotspot1',
            metadata: $data['metadata'] ?? []
        );
    }

    public function toArray(): array
    {
        return [
            'username' => $this->username,
            'ip_address' => $this->ipAddress,
            'mac_address' => $this->macAddress,
            'interface' => $this->interface,
            'connected_since' => $this->connectedSince?->toISOString(),
            'last_active' => $this->lastActive?->toISOString(),
            'uptime_seconds' => $this->uptimeSeconds,
            'bytes_in' => $this->bytesIn,
            'bytes_out' => $this->bytesOut,
            'packets_in' => $this->packetsIn,
            'packets_out' => $this->packetsOut,
            'upload_speed_bps' => $this->uploadSpeedBps,
            'download_speed_bps' => $this->downloadSpeedBps,
            'total_bytes' => $this->bytesIn + $this->bytesOut,
            'hostname' => $this->hostname,
            'device_type' => $this->deviceType,
            'connection_type' => $this->connectionType,
            'status' => $this->status,
            'server' => $this->server,
            'metadata' => $this->metadata,
            'upload_speed_formatted' => $this->formatSpeed($this->uploadSpeedBps),
            'download_speed_formatted' => $this->formatSpeed($this->downloadSpeedBps),
            'total_data_formatted' => $this->formatBytes($this->bytesIn + $this->bytesOut),
            'uptime_formatted' => $this->formatUptime(),
            'is_active' => $this->isActive(),
            'is_idle' => $this->isIdle(),
        ];
    }

    public function toDatabaseArray(): array
    {
        return [
            'username' => $this->username,
            'ip_address' => $this->ipAddress,
            'mac_address' => $this->macAddress,
            'interface' => $this->interface,
            'connected_since' => $this->connectedSince,
            'last_active' => $this->lastActive,
            'uptime_seconds' => $this->uptimeSeconds,
            'bytes_in' => $this->bytesIn,
            'bytes_out' => $this->bytesOut,
            'packets_in' => $this->packetsIn,
            'packets_out' => $this->packetsOut,
            'upload_speed_bps' => $this->uploadSpeedBps,
            'download_speed_bps' => $this->downloadSpeedBps,
            'hostname' => $this->hostname,
            'device_type' => $this->deviceType,
            'connection_type' => $this->connectionType,
            'status' => $this->status,
            'server' => $this->server,
            'metadata' => $this->metadata,
        ];
    }

    private static function parseMikrotikUptime(string $uptime): int
    {
        // Parse MikroTik uptime format: 1d2h3m4s or 01:02:03
        $seconds = 0;

        if (str_contains($uptime, 'd') || str_contains($uptime, 'h') || str_contains($uptime, 'm') || str_contains($uptime, 's')) {
            // Format: 1d2h3m4s
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
            // Format: 01:02:03
            $parts = explode(':', $uptime);
            $hours = (int) ($parts[0] ?? 0);
            $minutes = (int) ($parts[1] ?? 0);
            $secs = (int) ($parts[2] ?? 0);

            $seconds = ($hours * 3600) + ($minutes * 60) + $secs;
        }

        return $seconds;
    }

    private static function parseSpeed(string $rateLimit, string $direction): float
    {
        // Parse MikroTik rate limit format: 512k/2M
        $parts = explode('/', $rateLimit);

        if ($direction === 'upload') {
            $speed = $parts[0] ?? '0';
        } else {
            $speed = $parts[1] ?? $parts[0] ?? '0';
        }

        return self::parseSpeedString($speed);
    }

    private static function parseSpeedString(string $speed): float
    {
        $multiplier = 1;

        if (str_ends_with($speed, 'G')) {
            $multiplier = 1000000000;
            $speed = substr($speed, 0, -1);
        } elseif (str_ends_with($speed, 'M')) {
            $multiplier = 1000000;
            $speed = substr($speed, 0, -1);
        } elseif (str_ends_with($speed, 'k')) {
            $multiplier = 1000;
            $speed = substr($speed, 0, -1);
        }

        return ((float) $speed) * $multiplier;
    }

    private static function guessDeviceType(?string $userAgent): string
    {
        if (!$userAgent) {
            return 'unknown';
        }

        $userAgent = strtolower($userAgent);

        if (str_contains($userAgent, 'android')) {
            return 'android';
        }

        if (str_contains($userAgent, 'iphone') || str_contains($userAgent, 'ipad')) {
            return 'ios';
        }

        if (str_contains($userAgent, 'windows')) {
            return 'windows';
        }

        if (str_contains($userAgent, 'mac')) {
            return 'mac';
        }

        if (str_contains($userAgent, 'linux')) {
            return 'linux';
        }

        return 'other';
    }

    private function formatUptime(): string
    {
        $hours = floor($this->uptimeSeconds / 3600);
        $minutes = floor(($this->uptimeSeconds % 3600) / 60);
        $seconds = $this->uptimeSeconds % 60;

        if ($hours > 0) {
            return sprintf('%dh %dm %ds', $hours, $minutes, $seconds);
        }

        if ($minutes > 0) {
            return sprintf('%dm %ds', $minutes, $seconds);
        }

        return sprintf('%ds', $seconds);
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

    private function formatSpeed(float $bps): string
    {
        $units = ['bps', 'Kbps', 'Mbps', 'Gbps'];
        $index = 0;

        while ($bps >= 1000 && $index < count($units) - 1) {
            $bps /= 1000;
            $index++;
        }

        return round($bps, 2) . ' ' . $units[$index];
    }

    public function isActive(): bool
    {
        return $this->status === 'active' || $this->status === 'connected';
    }

    public function isIdle(): bool
    {
        // Consider idle if no data transfer in last 5 minutes
        if (!$this->lastActive) {
            return false;
        }

        return $this->lastActive->diffInMinutes(Carbon::now()) > 5;
    }

    public function getDataUsagePercentage(?int $limitBytes = null): ?float
    {
        if (!$limitBytes || $limitBytes <= 0) {
            return null;
        }

        $totalBytes = $this->bytesIn + $this->bytesOut;
        return min(100, ($totalBytes / $limitBytes) * 100);
    }

    public function getConnectionDuration(): \DateInterval
    {
        if (!$this->connectedSince) {
            return Carbon::now()->diff(Carbon::now());
        }

        return $this->connectedSince->diff(Carbon::now());
    }
}
