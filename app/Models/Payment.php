<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Builder;

class Payment extends BaseModel
{
    use HasFactory;
    protected $table = 'payments';

    protected $fillable = [
        'uuid',
        'customer_id',
        'transaction_id',
        'reference',
        'amount',
        'currency',
        'status',
        'payment_method',
        'provider',
        'paid_at',
        'failed_at',
        'refunded_at',
        'gateway_response',
        'metadata',
        'audit_trail',
        'resolved_at',
        'resolution_notes',
        'disputed_at',
        'dispute_reason',
        'tenant_id'
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'paid_at' => 'datetime',
        'failed_at' => 'datetime',
        'refunded_at' => 'datetime',
        'resolved_at' => 'datetime',
        'disputed_at' => 'datetime',
        'gateway_response' => 'array',
        'metadata' => 'array',
        'audit_trail' => 'array'
    ];

    protected $appends = [
        'formatted_amount',
        'is_successful',
        'is_failed',
        'is_pending'
    ];

    /**
     * Relationships
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function voucher(): HasOne
    {
        return $this->hasOne(Voucher::class);
    }

    /**
     * Scopes
     */
    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('status', 'completed');
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending');
    }

    public function scopeFailed(Builder $query): Builder
    {
        return $query->where('status', 'failed');
    }

    public function scopeByCustomer(Builder $query, string $customerId): Builder
    {
        return $query->where('customer_id', $customerId);
    }

    public function scopeByReference(Builder $query, string $reference): Builder
    {
        return $query->where('reference', $reference);
    }

    public function scopeByTransactionId(Builder $query, string $transactionId): Builder
    {
        return $query->where('transaction_id', $transactionId);
    }

    public function scopeAmountGreaterThan(Builder $query, float $amount): Builder
    {
        return $query->where('amount', '>', $amount);
    }

    public function scopeAmountLessThan(Builder $query, float $amount): Builder
    {
        return $query->where('amount', '<', $amount);
    }

    public function scopeBetweenDates(Builder $query, string $startDate, string $endDate): Builder
    {
        return $query->whereBetween('created_at', [$startDate, $endDate]);
    }

    /**
     * Accessors
     */
    public function getFormattedAmountAttribute(): string
    {
        return number_format($this->amount, 2);
    }

    public function getIsSuccessfulAttribute(): bool
    {
        return $this->status === 'completed';
    }

    public function getIsFailedAttribute(): bool
    {
        return $this->status === 'failed';
    }

    public function getIsPendingAttribute(): bool
    {
        return $this->status === 'pending';
    }

    public function getProviderNameAttribute(): string
    {
        return match($this->provider) {
            'collectug' => 'Collect UG',
            'stripe' => 'Stripe',
            'paypal' => 'PayPal',
            default => ucfirst($this->provider)
        };
    }

    /**
     * Business Logic
     */
    public function markAsCompleted(string $reference = null): bool
    {
        $updateData = [
            'status' => 'completed',
            'paid_at' => now()
        ];

        if ($reference) {
            $updateData['reference'] = $reference;
        }

        return $this->update($updateData);
    }

    public function markAsFailed(string $reason = null): bool
    {
        $updateData = [
            'status' => 'failed',
            'failed_at' => now()
        ];

        if ($reason) {
            $this->metadata = array_merge($this->metadata ?? [], [
                'failure_reason' => $reason,
                'failed_at' => now()->toISOString()
            ]);
        }

        return $this->update($updateData);
    }

    public function markAsRefunded(): bool
    {
        return $this->update([
            'status' => 'refunded',
            'refunded_at' => now()
        ]);
    }

    public function isRefundable(): bool
    {
        return $this->is_successful &&
            !$this->refunded_at &&
            $this->paid_at->diffInDays(now()) <= 30; // 30-day refund window
    }

    public function updateGatewayResponse(array $response): bool
    {
        $currentResponse = $this->gateway_response ?? [];

        return $this->update([
            'gateway_response' => array_merge($currentResponse, $response)
        ]);
    }
}
