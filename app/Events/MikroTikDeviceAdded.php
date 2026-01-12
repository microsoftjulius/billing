<?php

namespace App\Events;

use App\Models\MikroTikDevice;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MikroTikDeviceAdded implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public MikroTikDevice $device
    ) {}

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): array
    {
        return [
            new Channel('mikrotik-devices'),
        ];
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'action' => 'added',
            'device' => [
                'id' => $this->device->id,
                'name' => $this->device->name,
                'ip_address' => $this->device->ip_address,
                'location' => $this->device->location,
                'status' => $this->device->status,
                'last_seen' => $this->device->last_seen?->toISOString(),
                'uptime_seconds' => $this->device->uptime_seconds,
                'created_at' => $this->device->created_at->toISOString(),
                'has_configuration' => $this->device->hasConfiguration(),
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
        return 'MikroTikDeviceAdded';
    }
}