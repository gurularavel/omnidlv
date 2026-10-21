<?php

namespace App\Http\Resources;

use App\Models\TaskPlan;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin TaskPlan */
class TaskPlanResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'hub_id' => $this->hub_id,
            'courier_id' => $this->courier_id,
            'status_slug' => $this->status_slug,
            'slot' => $this->slot,
            'starts_at' => $this->starts_at,
            'ends_at' => $this->ends_at,
            'quantity' => $this->quantity,
            'distance' => $this->distance,
            'duration' => $this->duration,
            'solver' => $this->solver,
            'router' => $this->router,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'courier' => $this->whenLoaded('courier', fn () => $this->courier ? [
                'id' => $this->courier->id,
                'name' => $this->courier->name,
                'phone_country' => $this->courier->phone_country,
                'phone' => $this->courier->phone,
            ] : null),
            'geometry' => $this->when($request->boolean('geometry'), $this->geometry),
            'tasks' => TaskResource::collection($this->whenLoaded('tasks')),
        ];
    }
}
