<?php

namespace App\Services;

use App\Enums\AssignmentType;
use App\Enums\Channel;
use App\Enums\StatusCategory;
use App\Enums\TaskType;
use App\Exceptions\DomainException;
use App\Exceptions\DuplicateTaskException;
use App\Models\Hub;
use App\Models\Status;
use App\Models\Task;
use App\Models\Team;
use App\Models\User;
use App\Services\Geocoding\Geocoder;
use App\Services\Planning\AutoAssigner;
use App\Support\CurrentCompany;
use App\Support\GeoPoint;
use App\Support\Phone;
use App\Support\TrackingCode;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Creates and updates tasks from the QDelivery-compatible payload shared by the API,
 * the dispatcher panel and spreadsheet import. Input is expected to be validated already
 * (see App\Http\Requests\Concerns\TaskRules); this class applies business rules.
 */
class TaskService
{
    public const ASSIGNMENT_OPTION_DEFAULTS = [
        'team_id' => null,
        'courier_online_status' => 'all',
        'obligation' => 'mandatory',
        'courier_duty_status' => 'idle',
        'distribution' => 'balanced',
        'min_capacity' => null,
        'courier_shift_status' => 'working',
        'team_id_by_polygon' => false,
    ];

    public const SLOT_OPTION_DEFAULTS = [
        'pool' => true,
        'tour' => true,
        'dispatch_early' => false,
        'from_hub' => true,
        'accelerate' => false,
        'unassigned_strategy' => 'nearest_plan',
    ];

    public function __construct(
        private readonly Geocoder $geocoder,
        private readonly StatusMachine $statuses,
        private readonly AssignmentService $assignments,
        private readonly AutoAssigner $autoAssigner,
        private readonly CurrentCompany $tenant,
    ) {}

    public function create(array $data, Channel $channel, ?User $actor = null): Task
    {
        $type = TaskType::from($data['action_slug'] ?? TaskType::DropOff->value);
        $this->guardDuplicate($data['integration_id'] ?? null, $type);

        $hub = Hub::query()->findOrFail($data['hub_id']);
        $company = $this->tenant->getOrFail();

        $task = new Task;
        $task->forceFill([
            'company_id' => $company->id,
            'owner_id' => $actor?->id,
            'hub_id' => $hub->id,
            'integration_id' => $data['integration_id'] ?? null,
            'tracking_code' => $this->trackingCode($data['tracking_code'] ?? null),
            'type_slug' => $type,
            'obligation_slug' => $data['assignment_options']['obligation'] ?? 'mandatory',
            'channel' => $channel,
        ]);

        $this->fillCommon($task, $data, $hub, isCreate: true);

        $assignmentType = AssignmentType::from($data['assignment']['type'] ?? AssignmentType::Pool->value);
        $task->assignment_type = $assignmentType;

        if (! empty($task->assignment_options['team_id_by_polygon']) && $task->team_id === null) {
            $point = $task->targetPoint();
            $task->team_id = $point ? Team::forPoint($point, $hub->id)?->id : null;
        }

        return DB::transaction(function () use ($task, $data, $channel, $actor, $assignmentType) {
            $initial = Status::defaultFor(StatusCategory::Created);
            $task->forceFill([
                'status_id' => $initial->id,
                'status_category_slug' => StatusCategory::Created,
                'status_at' => now(),
            ])->save();

            $task->statusLogs()->create([
                'status_id' => $initial->id,
                'status_category_slug' => StatusCategory::Created,
                'user_id' => $actor?->id,
                'role_slug' => $actor?->roleIn($task->company_id)?->value ?? ($channel === Channel::Api ? 'integration' : null),
                'channel' => $channel,
            ]);

            $this->syncItems($task, $data['items'] ?? []);

            match ($assignmentType) {
                AssignmentType::SelectedCourier => $this->assignments->assign(
                    $task, User::query()->findOrFail($data['assignment']['courier_id']), $channel, $actor,
                ),
                AssignmentType::Pool => $this->statuses->toCategory($task, StatusCategory::Pool, $channel, $actor),
                // No eligible courier now: the task waits in Created for tasks:assign-pending.
                AssignmentType::CapacityAware, AssignmentType::CapacityUnaware => $this->autoAssigner->assign($task),
                // Slot tasks wait for their slot to close (SlotService).
                default => null,
            };

            return $task->refresh();
        });
    }

    public function update(Task $task, array $data, Channel $channel, ?User $actor = null): Task
    {
        if ($task->isFinal()) {
            throw new DomainException(__('tasks.errors.final_status'), 'task_final');
        }

        if (array_key_exists('integration_id', $data) && $data['integration_id'] !== $task->integration_id) {
            $this->guardDuplicate($data['integration_id'], $task->type_slug, $task->id);
            $task->integration_id = $data['integration_id'];
        }

        if (isset($data['action_slug'])) {
            $task->type_slug = TaskType::from($data['action_slug']);
        }

        $hub = isset($data['hub_id']) ? Hub::query()->findOrFail($data['hub_id']) : $task->hub;
        $task->hub_id = $hub->id;

        $this->fillCommon($task, $data, $hub, isCreate: false);

        return DB::transaction(function () use ($task, $data) {
            $task->save();

            if (array_key_exists('items', $data)) {
                $this->syncItems($task, $data['items'] ?? []);
            }

            return $task->refresh();
        });
    }

    public function setLocked(Task $task, bool $locked): Task
    {
        $task->forceFill(['is_locked' => $locked])->save();

        return $task;
    }

    private function fillCommon(Task $task, array $data, Hub $hub, bool $isCreate): void
    {
        $simple = [
            'slot', 'slot_auto_closing_time', 'quantity', 'unit_quantity', 'service_time', 'response_time',
            'notes', 'recipient_notes', 'priority', 'payment_type', 'amount', 'currency',
        ];

        foreach ($simple as $field) {
            if (array_key_exists($field, $data)) {
                $task->{$field} = $data[$field];
            }
        }

        foreach (['starts_at', 'ends_at', 'slot_starts_at', 'slot_ends_at'] as $field) {
            if (array_key_exists($field, $data)) {
                // Times without an offset are UTC, as in the QDelivery contract.
                $task->{$field} = $data[$field] !== null ? Carbon::parse($data[$field], 'UTC')->utc() : null;
            }
        }

        if ($task->slot !== null && $task->slot_starts_at === null) {
            [$task->slot_starts_at, $task->slot_ends_at] = [$task->starts_at, $task->ends_at];
        }

        if (array_key_exists('tags', $data)) {
            $task->tags = array_values(array_unique(array_map('strval', $data['tags'] ?? [])));
        } elseif ($isCreate) {
            $task->tags = [];
        }

        if (array_key_exists('locked', $data)) {
            $task->is_locked = (bool) $data['locked'];
        }

        if (array_key_exists('confirmed', $data)) {
            $task->is_confirmed = (bool) $data['confirmed'];
        }

        if ($isCreate || array_key_exists('assignment_options', $data)) {
            $task->assignment_options = array_merge(
                self::ASSIGNMENT_OPTION_DEFAULTS,
                $isCreate ? [] : (array) $task->assignment_options,
                Arr::only($data['assignment_options'] ?? [], array_keys(self::ASSIGNMENT_OPTION_DEFAULTS)),
            );

            $teamId = $task->assignment_options['team_id'] ?? null;
            $task->team_id = $teamId !== null ? Team::query()->findOrFail($teamId)->id : $task->team_id;
        }

        if ($isCreate || array_key_exists('slot_options', $data)) {
            $task->slot_options = array_merge(
                self::SLOT_OPTION_DEFAULTS,
                $isCreate ? [] : (array) $task->slot_options,
                Arr::only($data['slot_options'] ?? [], array_keys(self::SLOT_OPTION_DEFAULTS)),
            );
        }

        $this->fillRequirements($task, $data['requirements'] ?? null, $isCreate);
        $this->fillSides($task, $data, $hub, $isCreate);

        if ($task->ends_at !== null && $task->starts_at !== null && $task->ends_at->lessThan($task->starts_at)) {
            throw ValidationException::withMessages(['ends_at' => __('tasks.errors.ends_before_starts')]);
        }
    }

    private function fillRequirements(Task $task, ?array $requirements, bool $isCreate): void
    {
        if ($requirements === null && ! $isCreate) {
            return;
        }

        $r = $requirements ?? [];
        $map = [
            'delivery_code' => 'requires_delivery_code',
            'delivery_code_mandatory' => 'requires_delivery_code_mandatory',
            'pod_photo' => 'requires_pod_photo',
            'pod_photo_mandatory' => 'requires_pod_photo_mandatory',
            'dont_ring_the_bell' => 'required_dont_ring_the_bell',
            'contactless' => 'requires_contactless',
        ];

        foreach ($map as $input => $column) {
            if ($isCreate || array_key_exists($input, $r)) {
                $task->{$column} = (bool) ($r[$input] ?? false);
            }
        }

        // "mandatory" implies "requested": a mandatory code nobody is asked for makes no sense.
        $task->requires_delivery_code = $task->requires_delivery_code || $task->requires_delivery_code_mandatory;
        $task->requires_pod_photo = $task->requires_pod_photo || $task->requires_pod_photo_mandatory;

        if (! empty($r['custom_delivery_code'])) {
            $task->delivery_code = (string) $r['custom_delivery_code'];
        } elseif ($task->requires_delivery_code && $task->delivery_code === null) {
            $task->delivery_code = str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
        }
    }

    private function fillSides(Task $task, array $data, Hub $hub, bool $isCreate): void
    {
        $type = $task->type_slug;

        foreach (['origin', 'destination'] as $side) {
            $input = $data[$side] ?? null;

            // The hub is the default origin of a drop-off and the default destination of a pickup.
            $hubSide = $type === TaskType::DropOff ? 'origin' : 'destination';
            if ($input === null && $isCreate && $side === $hubSide) {
                $input = ['hub_id' => $hub->id];
            }

            if ($input === null) {
                continue;
            }

            $this->fillSide($task, $side, $input);
        }

        if ($isCreate && $task->destination_address === null && $task->destination_hub_id === null) {
            throw ValidationException::withMessages(['destination' => __('tasks.errors.destination_required')]);
        }
    }

    private function fillSide(Task $task, string $side, array $input): void
    {
        if (! empty($input['hub_id'])) {
            $hub = Hub::query()->findOrFail($input['hub_id']);
            $task->forceFill([
                "{$side}_hub_id" => $hub->id,
                "{$side}_address" => $hub->address,
                "{$side}_address_building" => $hub->address_building,
                "{$side}_address_floor" => $hub->address_floor,
                "{$side}_address_apartment" => $hub->address_apartment,
                "{$side}_address_description" => null,
                "{$side}_name" => $input['name'] ?? $hub->name,
                "{$side}_lat" => $hub->lat,
                "{$side}_lng" => $hub->lng,
                "is_{$side}_resolved" => true,
            ]);
        } else {
            $task->forceFill([
                "{$side}_hub_id" => null,
                "{$side}_address" => $input['address'] ?? $task->{"{$side}_address"},
                "{$side}_address_building" => $input['address_building'] ?? null,
                "{$side}_address_floor" => $input['address_floor'] ?? null,
                "{$side}_address_apartment" => $input['address_apartment'] ?? null,
                "{$side}_address_description" => $input['address_description'] ?? null,
                "{$side}_name" => $input['name'] ?? $task->{"{$side}_name"},
            ]);

            $this->resolvePoint($task, $side, $input);
        }

        if (array_key_exists('phone', $input) || array_key_exists('phone_country', $input)) {
            $country = strtoupper($input['phone_country'] ?? $this->tenant->get()?->country ?? 'AZ');
            $phone = Phone::normalize($input['phone'] ?? null, $country);

            if (($input['phone'] ?? null) !== null && $phone === null) {
                throw ValidationException::withMessages(["{$side}.phone" => __('validation.phone')]);
            }

            $task->forceFill([
                "{$side}_phone" => $phone,
                "{$side}_phone_country" => $phone ? (Phone::regionOf($phone) ?? $country) : null,
            ]);
        }
    }

    private function resolvePoint(Task $task, string $side, array $input): void
    {
        $point = GeoPoint::tryFrom($input['lat'] ?? null, $input['lng'] ?? null);

        if ($point === null && ! empty($input['address'])) {
            $point = $this->geocoder->geocode($input['address'], $this->tenant->get()?->country);
        }

        if ($point === null && empty($input['allow_unresolved'])) {
            throw ValidationException::withMessages(["{$side}.address" => __('tasks.errors.address_unresolved')]);
        }

        $task->forceFill([
            "{$side}_lat" => $point?->lat,
            "{$side}_lng" => $point?->lng,
            "is_{$side}_resolved" => $point !== null,
        ]);
    }

    /** Items are matched by integration_id: existing ones are updated, missing ones removed. */
    private function syncItems(Task $task, array $items): void
    {
        $keep = [];

        foreach ($items as $item) {
            $keep[] = $item['integration_id'];
            $task->items()->updateOrCreate(
                ['integration_id' => $item['integration_id']],
                Arr::only($item, ['name', 'description', 'notes', 'quantity', 'currency', 'amount', 'barcode']),
            );
        }

        $task->items()->whereNotIn('integration_id', $keep)->delete();
    }

    private function guardDuplicate(?string $integrationId, TaskType $type, ?int $ignoreId = null): void
    {
        if ($integrationId === null || $integrationId === '') {
            return;
        }

        $existing = Task::query()
            ->where('integration_id', $integrationId)
            ->where('type_slug', $type->value)
            ->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))
            ->first();

        if ($existing) {
            throw new DuplicateTaskException($existing);
        }
    }

    private function trackingCode(?string $requested): string
    {
        if ($requested === null || $requested === '') {
            return TrackingCode::generate();
        }

        if (Task::withoutGlobalScopes()->where('tracking_code', $requested)->exists()) {
            throw ValidationException::withMessages(['tracking_code' => __('validation.unique', ['attribute' => 'tracking_code'])]);
        }

        return $requested;
    }
}
