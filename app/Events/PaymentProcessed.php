<?php

namespace App\Events;

use App\Models\Payment;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PaymentProcessed implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Payment $payment
    ) {}

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): array
    {
        return [
            new Channel('payments'),
        ];
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'payment' => [
                'id' => $this->payment->id,
                'customer_id' => $this->payment->customer_id,
                'voucher_id' => $this->payment->voucher_id,
                'gateway_id' => $this->payment->gateway_id,
                'amount' => $this->payment->amount,
                'currency' => $this->payment->currency,
                'status' => $this->payment->status,
                'gateway_transaction_id' => $this->payment->gateway_transaction_id,
                'gateway_reference' => $this->payment->gateway_reference,
                'processed_at' => $this->payment->processed_at?->toISOString(),
                'created_at' => $this->payment->created_at->toISOString(),
                'updated_at' => $this->payment->updated_at->toISOString(),
            ],
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'PaymentProcessed';
    }
}