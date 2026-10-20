<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Team */
class TeamResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'hub_id' => $this->hub_id,
            'integration_id' => $this->integration_id,
            'name' => $this->name,
            'is_default' => $this->is_default,
            'color' => $this->color,
            'polygon' => $this->polygon,
            'couriers_count' => $this->whenCounted('couriers'),
            'tags' => $this->tags ?? [],
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'hub' => new HubResource($this->whenLoaded('hub')),
        ];
    }
}
