<?php

namespace App\Services\Courier;

use App\Enums\StatusCategory;
use App\Enums\TaskType;
use App\Models\CourierLocation;
use App\Models\CourierShift;
use App\Models\Task;
use App\Models\User;
use App\Support\CurrentCompany;
use App\Support\Ui;

/**
 * Shapes data for the courier app. Unlike the public tracking page, the courier sees the
 * recipient's real phone and full address: they need them to deliver.
 */
class CourierPresenter
{
    public function __construct(private readonly CurrentCompany $tenant) {}

    public function board(User $courier): array
    {
        $tz = $this->tenant->get()?->timezone ?? config('app.timezone');
        $dayStart = now($tz)->startOfDay()->utc();

        $active = Task::query()
            ->with(['status', 'items:id,task_id,scanned_at'])
            ->where('courier_id', $courier->id)
            ->active()
            ->orderBy('route_order')
            ->orderBy('ends_at')
            ->get();

        $done = Task::query()
            ->with('status')
            ->where('courier_id', $courier->id)
            ->inCategory(StatusCategory::Completed, StatusCategory::Failed)
            ->where('ended_at', '>=', $dayStart)
            ->orderByDesc('ended_at')
            ->get();

        $teamIds = $courier->teams()->pluck('teams.id');
        $pool = Task::query()
            ->with('status')
            ->inCategory(StatusCategory::Pool)
            ->where(fn ($q) => $q->whereNull('team_id')->orWhereIn('team_id', $teamIds))
            ->orderBy('ends_at')
            ->limit(30)
            ->get();

        $shift = CourierShift::query()->where('user_id', $courier->id)->whereNull('ended_at')->latest('id')->first();
        $location = CourierLocation::query()->find($courier->id);

        return [
            'courier' => ['id' => $courier->id, 'name' => $courier->name, 'ink' => Ui::courierInk($courier)],
            'company' => $this->tenant->get()?->name,
            'shift' => [
                'active' => $shift !== null,
                'started_at' => $shift?->started_at?->toIso8601String(),
                'started_label' => $shift ? Ui::localTime($shift->started_at) : null,
            ],
            'last_seen_at' => $location?->last_seen_at?->toIso8601String(),
            'position' => $location?->point() ? ['lat' => $location->lat, 'lng' => $location->lng] : null,
            // The home screen is built around this one task.
            'next' => $active->isNotEmpty() ? $this->task($active->first()) : null,
            'active' => $active->map(fn (Task $t) => $this->summary($t))->values(),
            'pool' => $pool->map(fn (Task $t) => $this->summary($t))->values(),
            'done' => $done->map(fn (Task $t) => $this->summary($t))->values(),
            'counts' => [
                'active' => $active->count(),
                'pool' => $pool->count(),
                'delivered' => $done->where('status_category_slug', StatusCategory::Completed)->count(),
                'failed' => $done->where('status_category_slug', StatusCategory::Failed)->count(),
                'cash' => round((float) $active->where('payment_type', 'cash')->sum('amount'), 2),
            ],
        ];
    }

    public function summary(Task $t): array
    {
        $minutes = $t->isFinal() ? null : Ui::minutesLeft($t->ends_at);
        $target = $this->targetSide($t);

        return [
            'id' => $t->id,
            'code' => $t->tracking_code,
            'type' => $t->type_slug->value,
            'category' => $t->status_category_slug->value,
            'status' => $t->status->label(),
            'status_form' => Ui::statusStyle($t->status_category_slug)['form'],
            'name' => $t->{"{$target}_name"},
            'address' => $t->{"{$target}_address"},
            'lat' => $t->{"{$target}_lat"},
            'lng' => $t->{"{$target}_lng"},
            'minutes' => $minutes,
            'minutes_label' => $t->isFinal() ? Ui::localTime($t->ended_at) : Ui::minutes($minutes),
            'urgency' => Ui::urgency($minutes),
            'window' => Ui::localTime($t->starts_at).'–'.Ui::localTime($t->ends_at),
            'cash' => $t->payment_type === 'cash' && $t->amount ? (float) $t->amount : null,
            'currency' => $t->currency,
            'locked' => $t->is_locked,
            'url' => route('courier.task', $t),
        ];
    }

    public function task(Task $t): array
    {
        $t->loadMissing(['status', 'items', 'pods', 'hub']);
        $target = $this->targetSide($t);
        $other = $target === 'destination' ? 'origin' : 'destination';
        $category = $t->status_category_slug;
        $hasPhoto = $t->pods->where('type', 'photo')->isNotEmpty();
        $codeOk = $t->delivery_code_verified_at !== null;

        $canComplete = $category === StatusCategory::OnDelivery
            && (! $t->requires_pod_photo_mandatory || $hasPhoto)
            && (! $t->requires_delivery_code_mandatory || $codeOk);

        return [
            ...$this->summary($t),
            'status_slug' => $t->status->slug,
            'starts_at' => $t->starts_at?->toIso8601String(),
            'ends_at' => $t->ends_at?->toIso8601String(),
            'hub' => $t->hub?->name,
            'target' => $this->side($t, $target),
            'from' => $this->side($t, $other),
            'phone' => $t->{"{$target}_phone"},
            'notes' => $t->notes,
            'recipient_notes' => $t->recipient_notes,
            'quantity' => $t->quantity,
            'requirements' => [
                'photo' => $t->requires_pod_photo,
                'photo_mandatory' => $t->requires_pod_photo_mandatory,
                'code' => $t->requires_delivery_code,
                'code_mandatory' => $t->requires_delivery_code_mandatory,
                'dont_ring' => $t->required_dont_ring_the_bell,
                'contactless' => $t->requires_contactless,
            ],
            'code_verified' => $codeOk,
            'code_attempts_left' => max(0, 5 - $t->delivery_code_attempts),
            'items' => $t->items->map(fn ($i) => [
                'id' => $i->id, 'name' => $i->name, 'sku' => $i->integration_id, 'barcode' => $i->barcode,
                'quantity' => $i->quantity, 'scanned' => $i->scanned_at !== null,
            ])->values(),
            'pods' => $t->pods->map(fn ($p) => ['id' => $p->id, 'type' => $p->type, 'url' => $p->url()])->values(),
            'has_photo' => $hasPhoto,
            // What the courier can do next, in the order the app offers it.
            'next' => match (true) {
                $t->is_locked => null,
                $category === StatusCategory::Pool => 'claim',
                $category === StatusCategory::Assigned => 'pickup',
                $category === StatusCategory::InProgress => 'depart',
                $category === StatusCategory::OnDelivery && $t->status->slug !== 'arrived' => 'arrive',
                $category === StatusCategory::OnDelivery => 'complete',
                default => null,
            },
            'can_complete' => $canComplete,
            'can_fail' => in_array($category, StatusCategory::active(), true) && ! $t->is_locked,
            'navigate' => $t->{"{$target}_lat"} ? [
                'google' => "https://www.google.com/maps/dir/?api=1&destination={$t->{"{$target}_lat"}},{$t->{"{$target}_lng"}}",
                'waze' => "https://waze.com/ul?ll={$t->{"{$target}_lat"}},{$t->{"{$target}_lng"}}&navigate=yes",
            ] : null,
            'final' => $t->isFinal(),
        ];
    }

    /** Where the courier is heading next: the pickup point first, then the drop. */
    private function targetSide(Task $t): string
    {
        $beforePickup = in_array($t->status_category_slug, [StatusCategory::Pool, StatusCategory::Created, StatusCategory::Assigned], true);

        if ($t->type_slug === TaskType::Pickup) {
            return $beforePickup ? 'origin' : 'destination';
        }

        return $beforePickup && $t->origin_hub_id === null ? 'origin' : 'destination';
    }

    private function side(Task $t, string $side): array
    {
        return [
            'name' => $t->{"{$side}_name"},
            'address' => $t->{"{$side}_address"},
            'building' => $t->{"{$side}_address_building"},
            'floor' => $t->{"{$side}_address_floor"},
            'apartment' => $t->{"{$side}_address_apartment"},
            'description' => $t->{"{$side}_address_description"},
            'phone' => $t->{"{$side}_phone"},
            'lat' => $t->{"{$side}_lat"},
            'lng' => $t->{"{$side}_lng"},
        ];
    }
}
