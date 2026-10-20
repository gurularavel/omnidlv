<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Courier (a User with role courier) in QDelivery's shape: courier_detail and
 * courier_location nested, teams as {id, name}.
 *
 * @mixin \App\Models\User
 */
class CourierResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $detail = $this->whenLoaded('courierDetail');
        $detail = $detail instanceof \App\Models\CourierDetail ? $detail : null;
        $location = $this->relationLoaded('courierLocation') ? $this->courierLocation : null;

        return [
            'id' => $this->id,
            'integration_id' => $detail?->integration_id,
            'role_slug' => 'courier',
            'name' => $this->name,
            'email' => $this->email,
            'phone_country' => $this->phone_country,
            'phone' => $this->phone,
            'locale' => $this->locale,
            'avatar_url' => $this->avatar ? asset('storage/'.$this->avatar) : null,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'courier_detail' => $this->when($detail !== null, fn () => [
                'code' => $detail->code,
                'vehicle_license_plate' => $detail->vehicle_license_plate,
                'vehicle_type' => $detail->vehicle_type_slug?->label(),
                'vehicle_type_slug' => $detail->vehicle_type_slug?->value,
                'vehicle_description' => $detail->vehicle_description,
                'vehicle_color' => $detail->vehicle_color,
                'capacity' => $detail->capacity,
                'pool_capacity' => $detail->pool_capacity,
                'vehicle_icon' => $detail->vehicle_type_slug?->icon(),
                'notes' => $detail->notes,
                'tags' => $detail->tags ?? [],
            ]),
            'courier_location' => [
                'is_online' => (bool) $location?->is_online,
                'is_working' => (bool) $location?->is_working,
                'on_duty' => (bool) $location?->on_duty,
                'lat' => $location?->lat,
                'lng' => $location?->lng,
                'last_seen_at' => $location?->last_seen_at,
            ],
            'teams' => $this->whenLoaded('teams', fn () => $this->teams->map(fn ($t) => ['id' => $t->id, 'name' => $t->name])->values()),
            'rating' => number_format((float) ($detail?->rating ?? 0), 1),
            'scored_tasks_count' => $detail?->scored_tasks_count ?? 0,
            'current_tasks_of_active_company_count' => $this->whenHas('current_tasks_count'),
            'completed_or_failed_tasks_count' => $this->whenHas('finished_tasks_count'),
        ];
    }
}
