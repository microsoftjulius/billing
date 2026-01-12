<?php

namespace App\Events;

use App\Models\MikroTikDevice;
use App\Models\MikroTikConfigHistory;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MikroTikConfigurationChanged implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public MikroTikDevice $device,
        public MikroTikConfigHistory $configHistory,
        public string $changeType = 'update'
    ) {}

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): array
    {
        return [
            new Channel('mikrotik-configuration'),
            new Channel('mikrotik-device.' . $this->device->id),
        ];
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'action' => 'configuration_changed',
            'change_type' => $this->changeType,
            'device' => [
                'id' => $this->device->id,
                'name' => $this->device->name,
                'ip_address' => $this->device->ip_address,
                'has_configuration' => $this->device->hasConfiguration(),
                'updated_at' => $this->device->updated_at->toISOString(),
            ],
            'config_history' => [
                'id' => $this->configHistory->id,
                'change_type' => $this->configHistory->change_type,
                'changed_by' => $this->configHistory->changedBy?->name,
                'created_at' => $this->configHistory->created_at->toISOString(),
            ],
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'MikroTikConfigurationChanged';
    }
}