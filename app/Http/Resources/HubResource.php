<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Hub */
class HubResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'integration_id' => $this->integration_id,
            'name' => $this->name,
            'address' => $this->address,
            'address_building' => $this->address_building,
            'address_apartment' => $this->address_apartment,
            'address_floor' => $this->address_floor,
            'lat' => $this->lat,
            'lng' => $this->lng,
            'teams_count' => $this->whenCounted('teams'),
            'tags' => $this->tags ?? [],
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'teams' => TeamResource::collection($this->whenLoaded('teams')),
        ];
    }
}
