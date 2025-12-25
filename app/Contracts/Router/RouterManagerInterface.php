<?php

namespace App\Contracts\Router;

use App\DTOs\Router\VoucherDTO;
use App\DTOs\Router\UserConnectionDTO;

interface RouterManagerInterface
{
    public function createVoucher(VoucherDTO $voucher): bool;
    public function disableVoucher(string $voucherCode): bool;
    public function getUserConnections(string $username): array;
    public function getSystemResources(): array;
    public function getActiveUsers(): array;
    public function getActiveUsersCount(): int;
    public function getTotalUsersCount(): int;
    public function getUser(string $username): ?array;
    public function getInterfaceStats(): array;
    public function getHotspotProfiles(): array;
    public function removeExpiredUsers(): int;
    public function testConnection(): bool;
    public function reboot(): bool;
}
