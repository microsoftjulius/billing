<?php

namespace App\Events;

use App\Models\Voucher;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class VoucherActivated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Voucher $voucher
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
            'voucher' => [
                'id' => $this->voucher->id,
                'code' => $this->voucher->code,
                'customer_id' => $this->voucher->customer_id,
                'amount' => $this->voucher->amount,
                'duration_hours' => $this->voucher->duration_hours,
                'status' => $this->voucher->status,
                'activated_at' => $this->voucher->activated_at?->toISOString(),
                'expires_at' => $this->voucher->expires_at?->toISOString(),
                'mikrotik_device_id' => $this->voucher->mikrotik_device_id,
                'created_at' => $this->voucher->created_at->toISOString(),
                'updated_at' => $this->voucher->updated_at->toISOString(),
            ],
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'VoucherActivated';
    }
}