<?php

namespace App\Events;

use App\Support\GeoPoint;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;

class CourierLocationUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(
        public int $companyId,
        public int $courierId,
        public GeoPoint $at,
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('company.'.$this->companyId)];
    }

    public function broadcastAs(): string
    {
        return 'courier.location';
    }

    public function broadcastWith(): array
    {
        return ['courier_id' => $this->courierId, 'lat' => $this->at->lat, 'lng' => $this->at->lng];
    }
}
