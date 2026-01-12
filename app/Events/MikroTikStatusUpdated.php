<?php

namespace App\Events;

use App\Models\MikroTikDevice;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MikroTikStatusUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public MikroTikDevice $device,
        public string $action = 'updated'
    ) {}

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): array
    {
        return [
            new Channel('mikrotik-status'),
        ];
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'action' => $this->action,
            'device' => [
                'id' => $this->device->id,
                'name' => $this->device->name,
                'ip_address' => $this->device->ip_address,
                'location' => $this->device->location,
                'status' => $this->device->status,
                'last_seen' => $this->device->last_seen?->toISOString(),
                'uptime_seconds' => $this->device->uptime_seconds,
                'updated_at' => $this->device->updated_at->toISOString(),
                'active_users_count' => $this->device->active_users_count,
                'total_users_count' => $this->device->total_users_count,
            ],
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'MikroTikStatusUpdated';
    }
}