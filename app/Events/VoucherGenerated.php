<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

class VoucherGenerated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Collection $vouchers
    ) {}

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): array
    {
        return [
            new Channel('vouchers'),
        ];
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'vouchers' => $this->vouchers->map(function ($voucher) {
                return [
                    'id' => $voucher->id,
                    'code' => $voucher->code,
                    'customer_id' => $voucher->customer_id,
                    'amount' => $voucher->amount,
                    'duration_hours' => $voucher->duration_hours,
                    'status' => $voucher->status,
                    'activated_at' => $voucher->activated_at?->toISOString(),
                    'expires_at' => $voucher->expires_at?->toISOString(),
                    'mikrotik_device_id' => $voucher->mikrotik_device_id,
                    'created_at' => $voucher->created_at->toISOString(),
                    'updated_at' => $voucher->updated_at->toISOString(),
                ];
            })->toArray(),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'VoucherGenerated';
    }
}