<?php

namespace App\Events;

use App\Enums\Channel;
use App\Enums\StatusCategory;
use App\Models\Task;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TaskStatusChanged implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Task $task,
        public StatusCategory $from,
        public Channel $channel,
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('company.'.$this->task->company_id)];
    }

    public function broadcastAs(): string
    {
        return 'task.status';
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->task->id,
            'tracking_code' => $this->task->tracking_code,
            'status_id' => $this->task->status_id,
            'status_category_slug' => $this->task->status_category_slug->value,
            'from' => $this->from->value,
            'courier_id' => $this->task->courier_id,
            'status_at' => $this->task->status_at?->toIso8601String(),
        ];
    }
}
