<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class SmsLog extends BaseModel
{
    protected $table = 'sms_logs';

    protected $fillable = [
        'uuid',
        'customer_id',
        'recipient',
        'content',
        'sender_id',
        'message_id',
        'status',
        'delivery_status',
        'cost',
        'currency',
        'provider',
        'provider_response',
        'metadata',
        'sent_at',
        'delivered_at',
        'failed_at'
    ];

    protected $casts = [
        'cost' => 'decimal:2',
        'sent_at' => 'datetime',
        'delivered_at' => 'datetime',
        'failed_at' => 'datetime',
        'provider_response' => 'array',
        'metadata' => 'array'
    ];

    protected $appends = [
        'formatted_cost',
        'is_delivered',
        'is_failed'
    ];

    /**
     * Relationships
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function smsable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Scopes
     */
    public function scopeByRecipient($query, string $recipient)
    {
        return $query->where('recipient', $recipient);
    }

    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    public function scopeDelivered($query)
    {
        return $query->where('status', 'delivered');
    }

    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeToday($query)
    {
        return $query->whereDate('created_at', today());
    }

    public function scopeByProvider($query, string $provider)
    {
        return $query->where('provider', $provider);
    }

    /**
     * Accessors
     */
    public function getFormattedCostAttribute(): string
    {
        return number_format($this->cost, 2);
    }

    public function getIsDeliveredAttribute(): bool
    {
        return $this->status === 'delivered';
    }

    public function getIsFailedAttribute(): bool
    {
        return $this->status === 'failed';
    }

    public function getShortContentAttribute(): string
    {
        return strlen($this->content) > 50
            ? substr($this->content, 0, 50) . '...'
            : $this->content;
    }

    /**
     * Business Logic
     */
    public function markAsSent(string $messageId = null): bool
    {
        return $this->update([
            'status' => 'sent',
            'message_id' => $messageId,
            'sent_at' => now()
        ]);
    }

    public function markAsDelivered(): bool
    {
        return $this->update([
            'status' => 'delivered',
            'delivered_at' => now()
        ]);
    }

    public function markAsFailed(string $reason = null): bool
    {
        $updateData = [
            'status' => 'failed',
            'failed_at' => now()
        ];

        if ($reason) {
            $this->metadata = array_merge($this->metadata ?? [], [
                'failure_reason' => $reason
            ]);
        }

        return $this->update($updateData);
    }

    public function updateDeliveryStatus(string $status): bool
    {
        return $this->update([
            'delivery_status' => $status
        ]);
    }

    public function logProviderResponse(array $response): bool
    {
        $currentResponse = $this->provider_response ?? [];

        return $this->update([
            'provider_response' => array_merge($currentResponse, $response)
        ]);
    }
}
