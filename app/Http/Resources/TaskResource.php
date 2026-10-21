<?php

namespace App\Http\Resources;

use App\Models\Task;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Task in QDelivery's integration shape. Field names match the QDelivery API so existing
 * integrations can switch base URL and key without code changes.
 *
 * @mixin Task
 */
class TaskResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $sides = [];
        foreach (['origin', 'destination'] as $side) {
            foreach (['address', 'address_building', 'address_floor', 'address_apartment', 'address_description',
                'phone_country', 'phone', 'name', 'lat', 'lng'] as $field) {
                $sides["{$side}_{$field}"] = $this->{"{$side}_{$field}"};
            }
            $sides["{$side}_hub_id"] = $this->{"{$side}_hub_id"};
            $sides["is_{$side}_resolved"] = $this->{"is_{$side}_resolved"};
        }

        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'owner_id' => $this->owner_id,
            'hub_id' => $this->hub_id,
            'integration_id' => $this->integration_id,
            'tracking_code' => $this->tracking_code,
            'tracking_url' => route('tracking.show', $this->tracking_code),
            'type_slug' => $this->type_slug->value,
            'obligation_slug' => $this->obligation_slug,
            'channel' => $this->channel->value,
            'status_id' => $this->status_id,
            'status_category_slug' => $this->status_category_slug->value,
            'status_at' => $this->status_at,
            'assignment_type' => $this->assignment_type->value,
            'assignment_options' => $this->assignment_options,
            'slot_options' => $this->slot_options,
            'courier_id' => $this->courier_id,
            'team_id' => $this->team_id,
            'plan_id' => $this->plan_id,
            'slot' => $this->slot,
            'slot_starts_at' => $this->slot_starts_at,
            'slot_ends_at' => $this->slot_ends_at,
            'starts_at' => $this->starts_at,
            'ends_at' => $this->ends_at,
            ...$sides,
            'quantity' => $this->quantity,
            'unit_quantity' => $this->unit_quantity,
            'service_time' => $this->service_time,
            'response_time' => $this->response_time,
            'priority' => $this->priority,
            'notes' => $this->notes,
            'recipient_notes' => $this->recipient_notes,
            'requires_delivery_code' => $this->requires_delivery_code,
            'requires_delivery_code_mandatory' => $this->requires_delivery_code_mandatory,
            'requirements_delivery_code' => $this->requires_delivery_code ? $this->delivery_code : null,
            'requires_pod_photo' => $this->requires_pod_photo,
            'requires_pod_photo_mandatory' => $this->requires_pod_photo_mandatory,
            'required_dont_ring_the_bell' => $this->required_dont_ring_the_bell,
            'requires_contactless' => $this->requires_contactless,
            'locked' => $this->is_locked,
            'confirmed' => $this->is_confirmed,
            'payment_type' => $this->payment_type,
            'amount' => $this->amount !== null ? (float) $this->amount : null,
            'currency' => $this->currency,
            'tags' => $this->tags ?? [],
            'route_order' => $this->route_order,
            'distance' => $this->distance,
            'duration' => $this->duration,
            'eta_at' => $this->eta_at,
            'rating' => $this->rating,
            'comment' => $this->comment,
            'was_finalized_duly' => $this->was_finalized_duly,
            'was_delayed' => $this->was_delayed,
            'seen_at' => $this->seen_at,
            'claimed_at' => $this->claimed_at,
            'started_at' => $this->started_at,
            'ended_at' => $this->ended_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,

            'status' => $this->whenLoaded('status', fn () => [
                'id' => $this->status->id,
                'slug' => $this->status->slug,
                'status_category_slug' => $this->status->status_category_slug->value,
                'name' => $this->status->label(),
            ]),
            'courier' => $this->whenLoaded('courier', fn () => $this->courier ? [
                'id' => $this->courier->id,
                'name' => $this->courier->name,
                'phone_country' => $this->courier->phone_country,
                'phone' => $this->courier->phone,
            ] : null),
            'hub' => $this->whenLoaded('hub', fn () => [
                'id' => $this->hub->id,
                'integration_id' => $this->hub->integration_id,
                'name' => $this->hub->name,
            ]),
            'team' => $this->whenLoaded('team', fn () => $this->team ? ['id' => $this->team->id, 'name' => $this->team->name] : null),
            'items' => TaskItemResource::collection($this->whenLoaded('items')),
            // QDelivery calls the items "contents" in responses; keep both names.
            'contents' => TaskItemResource::collection($this->whenLoaded('items')),
            'status_logs' => TaskStatusLogResource::collection($this->whenLoaded('statusLogs')),
        ];
    }
}
