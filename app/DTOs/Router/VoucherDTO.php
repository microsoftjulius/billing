<?php

namespace App\DTOs\Router;

use Carbon\Carbon;
use Illuminate\Support\Str;

readonly class VoucherDTO
{
    public function __construct(
        public string  $code,
        public string  $password,
        public string  $profile,
        public int     $validityHours,
        public ?int    $dataLimitMB,
        public ?float  $price,
        public string  $currency,
        public ?string $customerName,
        public ?string $customerPhone,
        public ?string $customerEmail,
        public Carbon  $createdAt,
        public Carbon  $expiresAt,
        public array   $metadata,
        public string  $comment
    ) {}

    public static function create(
        string $code,
        string $password,
        string $profile,
        int $validityHours,
        ?int $dataLimitMB = null,
        ?float $price = null,
        string $currency = 'UGX',
        ?string $customerName = null,
        ?string $customerPhone = null,
        ?string $customerEmail = null,
        ?Carbon $createdAt = null,
        ?Carbon $expiresAt = null,
        array $metadata = [],
        ?string $comment = null
    ): self {
        $createdAt = $createdAt ?? Carbon::now();
        $expiresAt = $expiresAt ?? $createdAt->copy()->addHours($validityHours);
        $comment = $comment ?? self::generateComment(
            $customerName,
            $customerPhone,
            $price,
            $currency,
            $expiresAt,
            $createdAt
        );

        return new self(
            code: $code,
            password: $password,
            profile: $profile,
            validityHours: $validityHours,
            dataLimitMB: $dataLimitMB,
            price: $price,
            currency: $currency,
            customerName: $customerName,
            customerPhone: $customerPhone,
            customerEmail: $customerEmail,
            createdAt: $createdAt,
            expiresAt: $expiresAt,
            metadata: $metadata,
            comment: $comment
        );
    }

    public static function fromArray(array $data): self
    {
        return self::create(
            code: $data['code'] ?? $data['name'] ?? Str::random(8),
            password: $data['password'] ?? Str::random(8),
            profile: $data['profile'] ?? 'default',
            validityHours: $data['validity_hours'] ?? $data['validityHours'] ?? 24,
            dataLimitMB: $data['data_limit_mb'] ?? $data['dataLimitMB'] ?? null,
            price: $data['price'] ?? null,
            currency: $data['currency'] ?? 'UGX',
            customerName: $data['customer_name'] ?? $data['customerName'] ?? null,
            customerPhone: $data['customer_phone'] ?? $data['customerPhone'] ?? null,
            customerEmail: $data['customer_email'] ?? $data['customerEmail'] ?? null,
            createdAt: isset($data['created_at']) ? Carbon::parse($data['created_at']) : null,
            expiresAt: isset($data['expires_at']) ? Carbon::parse($data['expires_at']) : null,
            metadata: $data['metadata'] ?? [],
            comment: $data['comment'] ?? null
        );
    }

    public function toArray(): array
    {
        return [
            'code' => $this->code,
            'password' => $this->password,
            'profile' => $this->profile,
            'validity_hours' => $this->validityHours,
            'data_limit_mb' => $this->dataLimitMB,
            'price' => $this->price,
            'currency' => $this->currency,
            'customer_name' => $this->customerName,
            'customer_phone' => $this->customerPhone,
            'customer_email' => $this->customerEmail,
            'created_at' => $this->createdAt->toISOString(),
            'expires_at' => $this->expiresAt->toISOString(),
            'metadata' => $this->metadata,
            'comment' => $this->comment,
        ];
    }

    public function toMikrotikArray(): array
    {
        // Format for MikroTik API
        $data = [
            'name' => $this->code,
            'password' => $this->password,
            'profile' => $this->profile,
            'limit-uptime' => $this->formatUptimeLimit(),
            'comment' => $this->comment,
        ];

        // Add data limit if specified
        if ($this->dataLimitMB) {
            $data['limit-bytes-total'] = $this->dataLimitMB * 1024 * 1024; // Convert MB to bytes
        }

        return $data;
    }

    public function formatUptimeLimit(): string
    {
        // Convert hours to MikroTik format (e.g., 24:00:00)
        $hours = $this->validityHours;
        $days = floor($hours / 24);
        $remainingHours = $hours % 24;

        if ($days > 0) {
            return sprintf('%d:%02d:00', $days * 24 + $remainingHours, 0);
        }

        return sprintf('%02d:00:00', $hours);
    }

    private static function generateComment(
        ?string $customerName,
        ?string $customerPhone,
        ?float $price,
        string $currency,
        Carbon $expiresAt,
        Carbon $createdAt
    ): string {
        $commentParts = [];

        if ($customerName) {
            $commentParts[] = "Customer: {$customerName}";
        }

        if ($customerPhone) {
            $commentParts[] = "Phone: {$customerPhone}";
        }

        if ($price) {
            $commentParts[] = "Price: {$currency} " . number_format($price, 2);
        }

        $commentParts[] = "Expires: {$expiresAt->format('Y-m-d H:i')}";
        $commentParts[] = "Generated at: {$createdAt->format('Y-m-d H:i:s')}";

        return implode(' | ', $commentParts);
    }

    public function getValidityInSeconds(): int
    {
        return $this->validityHours * 3600;
    }

    public function getDataLimitInBytes(): ?int
    {
        return $this->dataLimitMB ? $this->dataLimitMB * 1024 * 1024 : null;
    }

    public function isValid(): bool
    {
        return !empty($this->code) &&
            !empty($this->password) &&
            !empty($this->profile) &&
            $this->validityHours > 0 &&
            $this->expiresAt > $this->createdAt;
    }

    public function willExpireWithin(int $hours): bool
    {
        return $this->expiresAt->diffInHours(Carbon::now()) <= $hours;
    }
}
