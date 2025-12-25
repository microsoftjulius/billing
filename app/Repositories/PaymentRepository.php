<?php

namespace App\Repositories;

use App\Contracts\Repositories\PaymentRepositoryInterface;
use App\Models\Payment;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class PaymentRepository implements PaymentRepositoryInterface
{
    public function find(string $id): ?Payment
    {
        return Cache::remember("payment.{$id}", 300, function () use ($id) {
            return Payment::with(['customer', 'voucher'])->find($id);
        });
    }

    public function findByTransactionId(string $transactionId): ?Payment
    {
        return Payment::where('transaction_id', $transactionId)
            ->with(['customer', 'voucher'])
            ->first();
    }

    public function findByReference(string $reference): ?Payment
    {
        return Payment::where('reference', $reference)
            ->with(['customer', 'voucher'])
            ->first();
    }

    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        return Payment::with(['customer'])
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
    }

    public function getTodayPayments(): array
    {
        return Cache::remember('payments.today', 300, function () {
            return [
                'total' => Payment::today()->count(),
                'completed' => Payment::today()->completed()->count(),
                'pending' => Payment::today()->pending()->count(),
                'failed' => Payment::today()->failed()->count(),
                'revenue' => Payment::today()->completed()->sum('amount')
            ];
        });
    }

    public function getRevenueByDateRange(string $startDate, string $endDate): array
    {
        return DB::table('payments')
            ->select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('COUNT(*) as total_payments'),
                DB::raw('SUM(CASE WHEN status = "completed" THEN amount ELSE 0 END) as revenue')
            )
            ->whereBetween('created_at', [$startDate, $endDate])
            ->groupBy(DB::raw('DATE(created_at)'))
            ->orderBy('date')
            ->get()
            ->toArray();
    }

    public function getFailedPayments(int $days = 7): array
    {
        return Payment::with(['customer'])
            ->failed()
            ->where('created_at', '>=', now()->subDays($days))
            ->orderBy('created_at', 'desc')
            ->get()
            ->toArray();
    }

    public function create(array $data): Payment
    {
        $payment = Payment::create($data);
        Cache::forget('payments.today');
        return $payment;
    }

    public function update(Payment $payment, array $data): bool
    {
        $updated = $payment->update($data);

        if ($updated) {
            Cache::forget("payment.{$payment->id}");
            Cache::forget('payments.today');
        }

        return $updated;
    }

    public function delete(Payment $payment): bool
    {
        $deleted = $payment->delete();

        if ($deleted) {
            Cache::forget("payment.{$payment->id}");
            Cache::forget('payments.today');
        }

        return $deleted;
    }
}
