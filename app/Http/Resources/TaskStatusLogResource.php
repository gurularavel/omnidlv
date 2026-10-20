<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\TaskStatusLog */
class TaskStatusLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'task_id' => $this->task_id,
            'status_id' => $this->status_id,
            'status_category_slug' => $this->status_category_slug->value,
            'user_id' => $this->user_id,
            'courier_id' => $this->courier_id,
            'role_slug' => $this->role_slug,
            'channel' => $this->channel->value,
            'notes' => $this->notes,
            'lat' => $this->lat,
            'lng' => $this->lng,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'status' => $this->whenLoaded('status', fn () => [
                'id' => $this->status->id,
                'status_category_slug' => $this->status->status_category_slug->value,
                'name' => $this->status->label(),
            ]),
        ];
    }
}
