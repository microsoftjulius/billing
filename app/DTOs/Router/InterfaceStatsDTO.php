<?php

namespace App\DTOs\Router;

readonly class InterfaceStatsDTO
{
    public function __construct(
        public string  $name,
        public string  $type,
        public string  $status,
        public ?string $macAddress,
        public ?string $ipAddress,
        public int     $mtu,
        public int     $rxBytes,
        public int     $txBytes,
        public int     $rxPackets,
        public int     $txPackets,
        public int     $rxErrors,
        public int     $txErrors,
        public int     $rxDrops,
        public int     $txDrops,
        public float   $rxRateBps,
        public float   $txRateBps,
        public ?string $comment,
        public bool    $isRunning,
        public bool    $isDisabled,
        public array   $metadata
    ) {}

    public static function fromMikrotikData(array $data): self
    {
        $status = isset($data['running']) && $data['running'] === 'true' ? 'running' :
            (isset($data['disabled']) && $data['disabled'] === 'true' ? 'disabled' : 'unknown');

        $isRunning = $status === 'running';
        $isDisabled = $status === 'disabled';

        return new self(
            name: $data['name'] ?? 'unknown',
            type: $data['type'] ?? 'ether',
            status: $status,
            macAddress: $data['mac-address'] ?? null,
            ipAddress: $data['address'] ?? null,
            mtu: isset($data['mtu']) ? (int) $data['mtu'] : 1500,
            rxBytes: isset($data['rx-byte']) ? (int) $data['rx-byte'] : 0,
            txBytes: isset($data['tx-byte']) ? (int) $data['tx-byte'] : 0,
            rxPackets: isset($data['rx-packet']) ? (int) $data['rx-packet'] : 0,
            txPackets: isset($data['tx-packet']) ? (int) $data['tx-packet'] : 0,
            rxErrors: isset($data['rx-error']) ? (int) $data['rx-error'] : 0,
            txErrors: isset($data['tx-error']) ? (int) $data['tx-error'] : 0,
            rxDrops: isset($data['rx-drop']) ? (int) $data['rx-drop'] : 0,
            txDrops: isset($data['tx-drop']) ? (int) $data['tx-drop'] : 0,
            rxRateBps: isset($data['rx-rate']) ? (float) $data['rx-rate'] : 0.0,
            txRateBps: isset($data['tx-rate']) ? (float) $data['tx-rate'] : 0.0,
            comment: $data['comment'] ?? null,
            isRunning: $isRunning,
            isDisabled: $isDisabled,
            metadata: $data
        );
    }

    public static function create(
        string $name,
        string $type,
        string $status,
        ?string $macAddress = null,
        ?string $ipAddress = null,
        int $mtu = 1500,
        int $rxBytes = 0,
        int $txBytes = 0,
        int $rxPackets = 0,
        int $txPackets = 0,
        int $rxErrors = 0,
        int $txErrors = 0,
        int $rxDrops = 0,
        int $txDrops = 0,
        float $rxRateBps = 0.0,
        float $txRateBps = 0.0,
        ?string $comment = null,
        ?bool $isRunning = null,
        ?bool $isDisabled = null,
        array $metadata = []
    ): self {
        $isRunning = $isRunning ?? ($status === 'running');
        $isDisabled = $isDisabled ?? ($status === 'disabled');

        return new self(
            name: $name,
            type: $type,
            status: $status,
            macAddress: $macAddress,
            ipAddress: $ipAddress,
            mtu: $mtu,
            rxBytes: $rxBytes,
            txBytes: $txBytes,
            rxPackets: $rxPackets,
            txPackets: $txPackets,
            rxErrors: $rxErrors,
            txErrors: $txErrors,
            rxDrops: $rxDrops,
            txDrops: $txDrops,
            rxRateBps: $rxRateBps,
            txRateBps: $txRateBps,
            comment: $comment,
            isRunning: $isRunning,
            isDisabled: $isDisabled,
            metadata: $metadata
        );
    }

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'type' => $this->type,
            'status' => $this->status,
            'mac_address' => $this->macAddress,
            'ip_address' => $this->ipAddress,
            'mtu' => $this->mtu,
            'rx_bytes' => $this->rxBytes,
            'tx_bytes' => $this->txBytes,
            'total_bytes' => $this->rxBytes + $this->txBytes,
            'rx_packets' => $this->rxPackets,
            'tx_packets' => $this->txPackets,
            'rx_errors' => $this->rxErrors,
            'tx_errors' => $this->txErrors,
            'rx_drops' => $this->rxDrops,
            'tx_drops' => $this->txDrops,
            'rx_rate_bps' => $this->rxRateBps,
            'tx_rate_bps' => $this->txRateBps,
            'comment' => $this->comment,
            'is_running' => $this->isRunning,
            'is_disabled' => $this->isDisabled,
            'metadata' => $this->metadata,
            'rx_rate_formatted' => $this->formatRate($this->rxRateBps),
            'tx_rate_formatted' => $this->formatRate($this->txRateBps),
            'total_data_formatted' => $this->formatBytes($this->rxBytes + $this->txBytes),
            'error_rate' => $this->calculateErrorRate(),
            'utilization_percentage' => $this->calculateUtilization(),
            'is_wan_interface' => $this->isWanInterface(),
            'is_lan_interface' => $this->isLanInterface(),
        ];
    }

    private function formatRate(float $bps): string
    {
        $units = ['bps', 'Kbps', 'Mbps', 'Gbps'];
        $index = 0;

        while ($bps >= 1000 && $index < count($units) - 1) {
            $bps /= 1000;
            $index++;
        }

        return round($bps, 2) . ' ' . $units[$index];
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

    public function calculateErrorRate(): float
    {
        $totalPackets = $this->rxPackets + $this->txPackets;

        if ($totalPackets === 0) {
            return 0.0;
        }

        $totalErrors = $this->rxErrors + $this->txErrors;
        return ($totalErrors / $totalPackets) * 100;
    }

    public function calculateUtilization(): float
    {
        // Simple utilization calculation based on rate
        $interfaceSpeedBps = match($this->type) {
            'ether' => 1000000000, // 1Gbps
            'wlan' => 300000000,   // 300Mbps
            'pppoe' => 100000000,  // 100Mbps
            default => 1000000000, // Default 1Gbps
        };

        $currentRate = $this->rxRateBps + $this->txRateBps;
        return min(100, ($currentRate / $interfaceSpeedBps) * 100);
    }

    public function isWanInterface(): bool
    {
        $wanKeywords = ['wan', 'internet', 'pppoe', 'pptp', 'l2tp'];
        $nameLower = strtolower($this->name);

        foreach ($wanKeywords as $keyword) {
            if (str_contains($nameLower, $keyword)) {
                return true;
            }
        }

        return false;
    }

    public function isLanInterface(): bool
    {
        $lanKeywords = ['lan', 'local', 'bridge', 'vlan'];
        $nameLower = strtolower($this->name);

        foreach ($lanKeywords as $keyword) {
            if (str_contains($nameLower, $keyword)) {
                return true;
            }
        }

        return !$this->isWanInterface();
    }

    public function getTotalBytes(): int
    {
        return $this->rxBytes + $this->txBytes;
    }

    public function getTotalPackets(): int
    {
        return $this->rxPackets + $this->txPackets;
    }

    public function getTotalErrors(): int
    {
        return $this->rxErrors + $this->txErrors;
    }

    public function getTotalDrops(): int
    {
        return $this->rxDrops + $this->txDrops;
    }

    public function getTotalRateBps(): float
    {
        return $this->rxRateBps + $this->txRateBps;
    }

    public function isHealthy(): bool
    {
        // Interface is healthy if:
        // 1. It's running (not disabled)
        // 2. Error rate is below 1%
        // 3. Not too many packet drops
        return $this->isRunning &&
            $this->calculateErrorRate() < 1.0 &&
            $this->getTotalDrops() < 100;
    }

    public function getHealthStatus(): string
    {
        if (!$this->isRunning) {
            return 'down';
        }

        $errorRate = $this->calculateErrorRate();
        if ($errorRate > 5.0) {
            return 'critical';
        }

        if ($errorRate > 1.0) {
            return 'warning';
        }

        if ($this->getTotalDrops() > 1000) {
            return 'warning';
        }

        return 'healthy';
    }
}
