<?php

namespace App\Services\Webhooks;

use App\Models\Hub;
use App\Models\Task;
use App\Models\User;

/**
 * Webhook bodies in QDelivery's shape, so a receiver written for QDelivery parses ours
 * unchanged. Fields QDelivery does not send (tracking_code, previous status) are additions only.
 */
class WebhookPayloads
{
    public function taskStatusChanged(Task $task, ?string $from = null): array
    {
        $task->loadMissing(['status', 'courier.courierDetail', 'courier.courierLocation']);

        return [
            'courier' => $task->courier ? $this->courier($task->courier) : null,
            'task' => [
                ...$this->task($task),
                'previous_status_category_slug' => $from,
            ],
        ];
    }

    public function courierLocationUpdated(User $courier): array
    {
        $courier->loadMissing(['courierDetail', 'courierLocation']);

        $tasks = Task::query()
            ->with('status')
            ->where('courier_id', $courier->id)
            ->active()
            ->orderBy('route_order')
            ->orderBy('ends_at')
            ->get();

        return [
            'courier' => $this->courier($courier),
            'tasks' => $tasks->map(fn (Task $t) => $this->task($t))->values()->all(),
        ];
    }

    public function courierWorkingStatusUpdated(User $courier): array
    {
        $courier->loadMissing(['courierDetail', 'courierLocation', 'teams']);
        $hubIds = $courier->teams->pluck('hub_id')->unique()->filter();

        $hubs = Hub::query()
            ->whereIn('id', $hubIds)
            ->with(['teams.couriers' => fn ($q) => $q
                ->with(['courierDetail', 'courierLocation'])
                ->withCount(['courierTasks as active_tasks_count' => fn ($t) => $t->active()]),
            ])
            ->get();

        return [
            'courier' => $this->courier($courier),
            'hubs' => $hubs->map(fn (Hub $hub) => [
                'id' => $hub->id,
                'integration_id' => $hub->integration_id,
                'name' => $hub->name,
                'couriers_count' => $hub->teams->flatMap->couriers->unique('id')->count(),
                'teams' => $hub->teams->map(fn ($team) => [
                    'id' => $team->id,
                    'integration_id' => $team->integration_id,
                    'name' => $team->name,
                    'couriers_count' => $team->couriers->count(),
                    'couriers_count_details' => $this->counts($team->couriers),
                ])->values()->all(),
            ])->values()->all(),
        ];
    }

    public function test(): array
    {
        return [
            'message' => 'Test delivery from OmniDLV. If you can read this, the endpoint works.',
            'sent_at' => now()->toIso8601String(),
        ];
    }

    public function courier(User $courier): array
    {
        $detail = $courier->courierDetail;
        $location = $courier->courierLocation;

        return [
            'id' => $courier->id,
            'name' => $courier->name,
            'avatar_url' => $courier->avatar ? asset('storage/'.$courier->avatar) : null,
            'phone_country' => $courier->phone_country,
            'phone' => $courier->phone,
            'courier_detail' => [
                'code' => $detail?->code,
                'vehicle_license_plate' => $detail?->vehicle_license_plate,
                'vehicle_type' => $detail?->vehicle_type_slug?->label(),
                'vehicle_type_slug' => $detail?->vehicle_type_slug?->value,
                'vehicle_description' => $detail?->vehicle_description,
                'vehicle_color' => $detail?->vehicle_color,
                'capacity' => $detail?->capacity,
                'pool_capacity' => $detail?->pool_capacity,
                'vehicle_icon' => $detail?->vehicle_type_slug?->icon(),
            ],
            'courier_location' => [
                'is_online' => (bool) $location?->is_online,
                'is_working' => (bool) $location?->is_working,
                'on_duty' => (bool) $location?->on_duty,
                'lat' => $location?->lat,
                'lng' => $location?->lng,
                'last_seen_at' => $location?->last_seen_at?->toIso8601String(),
            ],
        ];
    }

    public function task(Task $task): array
    {
        $lastNote = $task->statusLogs()->latest('id')->value('notes');

        return [
            'id' => $task->id,
            'integration_id' => $task->integration_id,
            'tracking_code' => $task->tracking_code,
            'status_category_slug' => $task->status_category_slug->value,
            'status_at' => $task->status_at?->toIso8601String(),
            'unit_quantity' => $task->unit_quantity,
            'amount' => $task->amount !== null ? (float) $task->amount : null,
            'status' => [
                'id' => $task->status->id,
                'name' => $task->status->label(),
                'notes' => $lastNote,
                'description' => $task->status->descriptionText(),
            ],
            'starts_at' => $task->starts_at?->toIso8601String(),
            'ends_at' => $task->ends_at?->toIso8601String(),
            'started_at' => $task->started_at?->toIso8601String(),
            'ended_at' => $task->ended_at?->toIso8601String(),
            'distance' => $task->distance,
            'duration' => $task->duration,
            // Filled once route planning runs; until then the receiver gets nulls, not guesses.
            'remaining_distance' => null,
            'remaining_time' => null,
            'tags' => $task->tags ?? [],
        ];
    }

    /** QDelivery's per-team breakdown of who is free, busy or full. */
    private function counts($couriers): array
    {
        $c = ['total' => 0, 'online' => 0, 'offline' => 0, 'working' => 0, 'resting' => 0, 'on_duty' => 0,
            'idle' => 0, 'fully_loaded' => 0, 'has_capacity' => 0, 'unloaded' => 0, 'unlimited_capacity' => 0];

        foreach ($couriers as $courier) {
            $location = $courier->courierLocation;
            $capacity = $courier->courierDetail?->capacity;
            $load = (int) $courier->active_tasks_count;

            $c['total']++;
            $location?->is_online ? $c['online']++ : $c['offline']++;
            $location?->is_working ? $c['working']++ : $c['resting']++;
            if ($location?->on_duty) {
                $c['on_duty']++;
            } elseif ($location?->is_working) {
                $c['idle']++;
            }

            if (! $capacity) {
                $c['unlimited_capacity']++;
            } elseif ($load >= $capacity) {
                $c['fully_loaded']++;
            } else {
                $c['has_capacity']++;
            }

            if ($load === 0) {
                $c['unloaded']++;
            }
        }

        return $c;
    }
}
