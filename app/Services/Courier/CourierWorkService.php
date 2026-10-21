<?php

namespace App\Services\Courier;

use App\Enums\Channel;
use App\Events\CourierLocationUpdated;
use App\Events\CourierWorkingStatusChanged;
use App\Exceptions\DomainException;
use App\Models\CourierLocation;
use App\Models\CourierShift;
use App\Models\Status;
use App\Models\Task;
use App\Models\TaskItem;
use App\Models\TaskPod;
use App\Models\User;
use App\Services\AssignmentService;
use App\Services\StatusMachine;
use App\Support\CurrentCompany;
use App\Support\GeoPoint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Everything a courier does from the app. The courier may only touch their own tasks and only
 * move them forward through the statuses below; dispatch-side moves (unassign, cancel) stay
 * in the panel.
 */
class CourierWorkService
{
    /** Status slugs a courier may set, per action. */
    public const ACTIONS = [
        'pickup' => 'picked_up',
        'depart' => 'on_the_way',
        'arrive' => 'arrived',
    ];

    public const COMPLETE_STATUSES = ['delivered', 'left_at_door', 'delivered_to_other'];

    public const FAIL_STATUSES = ['recipient_not_available', 'wrong_address', 'refused', 'damaged', 'failed'];

    public function __construct(
        private readonly StatusMachine $statuses,
        private readonly AssignmentService $assignments,
        private readonly CurrentCompany $tenant,
    ) {}

    public function startShift(User $courier, ?GeoPoint $at): CourierShift
    {
        return DB::transaction(function () use ($courier, $at) {
            $open = CourierShift::query()->where('user_id', $courier->id)->whereNull('ended_at')->first();

            $shift = $open ?? CourierShift::query()->create([
                'user_id' => $courier->id,
                'started_at' => now(),
                'start_lat' => $at?->lat,
                'start_lng' => $at?->lng,
            ]);

            $location = $this->location($courier);
            $wasWorking = (bool) $location->is_working;
            $location->fill([
                'is_working' => true,
                'is_online' => true,
                'last_seen_at' => now(),
                ...($at ? ['lat' => $at->lat, 'lng' => $at->lng] : []),
            ])->save();

            if (! $wasWorking) {
                $this->workingChanged($courier, true);
            }

            return $shift;
        });
    }

    public function endShift(User $courier): void
    {
        if (Task::query()->where('courier_id', $courier->id)->active()->exists()) {
            throw new DomainException(__('courier.errors.shift_has_tasks'), 'shift_has_tasks');
        }

        CourierShift::query()->where('user_id', $courier->id)->whereNull('ended_at')->update(['ended_at' => now()]);

        $location = $this->location($courier);
        $wasWorking = (bool) $location->is_working;
        $location->fill(['is_working' => false, 'on_duty' => false])->save();

        if ($wasWorking) {
            $this->workingChanged($courier, false);
        }
    }

    private function workingChanged(User $courier, bool $working): void
    {
        $companyId = $this->tenant->getOrFail()->id;

        DB::afterCommit(fn () => CourierWorkingStatusChanged::dispatch($companyId, $courier->id, $working));
    }

    /**
     * GPS ping. The latest state is always updated; history rows are thinned to one per 10 s,
     * and the broadcast to dispatchers to one per 15 s, so a chatty phone cannot flood either.
     */
    public function ping(User $courier, GeoPoint $at, array $meta = []): void
    {
        $location = $this->location($courier);
        $location->fill([
            'lat' => $at->lat,
            'lng' => $at->lng,
            'heading' => $meta['heading'] ?? null,
            'speed' => $meta['speed'] ?? null,
            'accuracy' => $meta['accuracy'] ?? null,
            'battery' => $meta['battery'] ?? null,
            'is_online' => true,
            'on_duty' => Task::query()->where('courier_id', $courier->id)->active()->exists(),
            'last_seen_at' => now(),
        ])->save();

        if (Cache::add("ping:{$courier->id}", 1, 10)) {
            $courier->locationPings()->create([
                'lat' => $at->lat,
                'lng' => $at->lng,
                'heading' => $meta['heading'] ?? null,
                'speed' => $meta['speed'] ?? null,
                'accuracy' => $meta['accuracy'] ?? null,
                'recorded_at' => now(),
            ]);
        }

        if (Cache::add("ping-broadcast:{$courier->id}", 1, 15)) {
            CourierLocationUpdated::dispatch($this->tenant->getOrFail()->id, $courier->id, $at);
        }
    }

    /** Forward step (pickup / depart / arrive). */
    public function step(Task $task, User $courier, string $action, ?GeoPoint $at, ?string $notes = null): Task
    {
        $this->guardOwn($task, $courier);
        $slug = self::ACTIONS[$action] ?? throw new DomainException(__('courier.errors.unknown_action'), 'unknown_action');

        return $this->statuses->transition($task, $this->status($slug), Channel::Courier, $courier, $notes, $at);
    }

    public function complete(Task $task, User $courier, string $slug, ?GeoPoint $at, ?string $notes = null, ?string $receivedBy = null): Task
    {
        $this->guardOwn($task, $courier);

        if (! in_array($slug, self::COMPLETE_STATUSES, true)) {
            throw new DomainException(__('courier.errors.unknown_action'), 'unknown_action');
        }

        if ($task->requires_pod_photo_mandatory && ! $task->pods()->where('type', 'photo')->exists()) {
            throw new DomainException(__('courier.errors.photo_required'), 'photo_required');
        }

        if ($task->requires_delivery_code_mandatory && $task->delivery_code_verified_at === null) {
            throw new DomainException(__('courier.errors.code_required'), 'code_required');
        }

        if ($receivedBy !== null) {
            $task->forceFill(['recipient_name_at_handover' => mb_substr($receivedBy, 0, 191)])->save();
        }

        return $this->statuses->transition($task, $this->status($slug), Channel::Courier, $courier, $notes, $at);
    }

    public function fail(Task $task, User $courier, string $slug, ?GeoPoint $at, ?string $notes = null): Task
    {
        $this->guardOwn($task, $courier);

        if (! in_array($slug, self::FAIL_STATUSES, true)) {
            throw new DomainException(__('courier.errors.unknown_action'), 'unknown_action');
        }

        return $this->statuses->transition($task, $this->status($slug), Channel::Courier, $courier, $notes, $at);
    }

    public function claim(Task $task, User $courier): Task
    {
        if ($task->team_id !== null && ! $courier->teams()->whereKey($task->team_id)->exists()) {
            throw new DomainException(__('courier.errors.not_your_team'), 'not_your_team');
        }

        return $this->assignments->claim($task, $courier);
    }

    /** Checks the recipient's code. Five wrong tries lock the code until the dispatcher steps in. */
    public function verifyCode(Task $task, User $courier, string $code): bool
    {
        $this->guardOwn($task, $courier);

        if ($task->delivery_code === null) {
            throw new DomainException(__('courier.errors.no_code'), 'no_code');
        }

        if ($task->delivery_code_attempts >= 5) {
            throw new DomainException(__('courier.errors.code_locked'), 'code_locked');
        }

        if (hash_equals((string) $task->delivery_code, trim($code))) {
            $task->forceFill(['delivery_code_verified_at' => now()])->save();

            return true;
        }

        Task::query()->whereKey($task->id)->increment('delivery_code_attempts');
        $task->refresh();

        return false;
    }

    public function addPod(Task $task, User $courier, string $type, UploadedFile $file, ?GeoPoint $at): TaskPod
    {
        $this->guardOwn($task, $courier);

        $path = $file->storeAs(
            "pods/{$task->company_id}/{$task->id}",
            Str::uuid().'.'.($type === 'signature' ? 'png' : ($file->extension() ?: 'jpg')),
            'local',
        );

        return $task->pods()->create([
            'courier_id' => $courier->id,
            'type' => $type,
            'path' => $path,
            'lat' => $at?->lat,
            'lng' => $at?->lng,
            'taken_at' => now(),
        ]);
    }

    /** Marks an item as scanned when the barcode (or SKU) matches. */
    public function scan(Task $task, User $courier, string $code): ?TaskItem
    {
        $this->guardOwn($task, $courier);
        $code = trim($code);

        $item = $task->items()
            ->where(fn ($q) => $q->where('barcode', $code)->orWhere('integration_id', $code))
            ->first();

        $item?->forceFill(['scanned_at' => now()])->save();

        return $item;
    }

    private function guardOwn(Task $task, User $courier): void
    {
        if ($task->courier_id !== $courier->id) {
            throw new DomainException(__('courier.errors.not_your_task'), 'not_your_task');
        }
    }

    private function status(string $slug): Status
    {
        return Status::query()->where('slug', $slug)->where('is_active', true)->firstOrFail();
    }

    private function location(User $courier): CourierLocation
    {
        return CourierLocation::query()->firstOrNew(['user_id' => $courier->id]);
    }
}
