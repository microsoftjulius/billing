<?php

namespace App\Contracts\Repositories;

use App\Models\Payment;
use Illuminate\Pagination\LengthAwarePaginator;

interface PaymentRepositoryInterface
{
    public function find(string $id): ?Payment;
    public function findByTransactionId(string $transactionId): ?Payment;
    public function findByReference(string $reference): ?Payment;
    public function paginate(int $perPage = 15): LengthAwarePaginator;
    public function getTodayPayments(): array;
    public function getRevenueByDateRange(string $startDate, string $endDate): array;
    public function getFailedPayments(int $days = 7): array;
    public function create(array $data): Payment;
    public function update(Payment $payment, array $data): bool;
    public function delete(Payment $payment): bool;
}
