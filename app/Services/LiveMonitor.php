<?php

namespace App\Services;

use App\Enums\StatusCategory;
use App\Models\Hub;
use App\Models\Task;
use App\Models\User;
use App\Support\CurrentCompany;
use App\Support\Ui;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Snapshot for the dispatcher's live monitor: the network drawn as transit lines (one per
 * courier, served stops filled, upcoming as rings) plus the exceptions that need a human.
 */
class LiveMonitor
{
    public function __construct(private readonly CurrentCompany $tenant) {}

    public function snapshot(?int $hubId = null): array
    {
        [$dayStart, $dayEnd] = $this->today();

        $hubs = Hub::query()->when($hubId, fn ($q) => $q->whereKey($hubId))->get(['id', 'name', 'lat', 'lng']);

        $couriers = User::query()->couriers()
            ->with(['courierLocation', 'teams:id,name,color'])
            ->orderBy('name')
            ->get();

        $tasks = Task::query()
            ->when($hubId, fn ($q) => $q->where('hub_id', $hubId))
            ->whereNotNull('courier_id')
            ->where(fn ($q) => $q->active()
                ->orWhere(fn ($q) => $q->inCategory(StatusCategory::Completed)->whereBetween('ended_at', [$dayStart, $dayEnd])))
            ->orderByRaw('ended_at IS NULL, ended_at')
            ->orderBy('route_order')
            ->orderBy('ends_at')
            ->get();

        $byCourier = $tasks->groupBy('courier_id');

        $lines = $couriers
            ->filter(fn (User $c) => $byCourier->has($c->id))
            ->map(fn (User $c) => $this->line($c, $byCourier->get($c->id), $hubs))
            ->values();

        return [
            'generated_at' => now()->toIso8601String(),
            'hubs' => $hubs->map(fn (Hub $h) => ['id' => $h->id, 'name' => $h->name, 'lat' => $h->lat, 'lng' => $h->lng])->values(),
            'couriers' => $couriers->map(fn (User $c) => [
                'id' => $c->id,
                'name' => $c->name,
                'initials' => $this->initials($c->name),
                'ink' => $this->inkFor($c),
                'lat' => $c->courierLocation?->lat,
                'lng' => $c->courierLocation?->lng,
                'is_online' => (bool) $c->courierLocation?->is_online,
                'is_working' => (bool) $c->courierLocation?->is_working,
                'active' => $byCourier->get($c->id)?->filter(fn (Task $t) => ! $t->isFinal())->count() ?? 0,
            ])->values(),
            'lines' => $lines,
            'attention' => $this->attention($hubId),
            'counts' => $this->counts($hubId, $dayStart, $dayEnd),
        ];
    }

    private function line(User $courier, Collection $tasks, Collection $hubs): array
    {
        $ink = $this->inkFor($courier);
        $stops = $tasks
            ->filter(fn (Task $t) => $t->targetPoint() !== null)
            ->map(function (Task $t) {
                $minutes = $t->isFinal() ? null : Ui::minutesLeft($t->ends_at);

                return [
                    'id' => $t->id,
                    'code' => $t->tracking_code,
                    'lat' => $t->targetPoint()->lat,
                    'lng' => $t->targetPoint()->lng,
                    'done' => $t->isFinal(),
                    'minutes' => $minutes,
                    'minutes_label' => $t->isFinal() ? Ui::localTime($t->ended_at) : Ui::minutes($minutes),
                    'urgency' => Ui::urgency($minutes),
                    'address' => Str::limit((string) $t->destination_address, 48),
                    'url' => route('tasks.show', $t),
                ];
            })
            ->values();

        $start = $courier->courierLocation?->point() ?? $hubs->firstWhere('id', $tasks->first()->hub_id)?->point();

        // The line starts where the courier is (or at the hub) and runs through upcoming stops.
        $upcoming = $stops->where('done', false)->map(fn ($s) => [$s['lat'], $s['lng']])->values()->all();
        $served = $stops->where('done', true)->map(fn ($s) => [$s['lat'], $s['lng']])->values()->all();
        $path = [...$served, ...($start ? [[$start->lat, $start->lng]] : []), ...$upcoming];

        return [
            'courier_id' => $courier->id,
            'name' => $courier->name,
            'ink' => $ink,
            'stops' => $stops,
            'path' => $path,
            'late' => $stops->where('urgency', 'late')->count(),
            'remaining' => count($upcoming),
            'served' => count($served),
        ];
    }

    private function attention(?int $hubId): array
    {
        return Task::query()
            ->with(['status', 'courier:id,name', 'courier.teams:id,color'])
            ->when($hubId, fn ($q) => $q->where('hub_id', $hubId))
            ->open()
            ->where(fn ($q) => $q
                ->where('ends_at', '<', now()->addMinutes(20))
                ->orWhereIn('status_category_slug', [StatusCategory::Created->value, StatusCategory::Pool->value])
                ->orWhere('is_destination_resolved', false))
            ->orderBy('ends_at')
            ->limit(60)
            ->get()
            ->map(function (Task $t) {
                $minutes = Ui::minutesLeft($t->ends_at);
                $reason = match (true) {
                    $minutes !== null && $minutes < 0 => 'late',
                    ! $t->is_destination_resolved => 'unresolved',
                    in_array($t->status_category_slug, [StatusCategory::Created, StatusCategory::Pool], true) => 'unassigned',
                    default => 'risk',
                };

                return [
                    'id' => $t->id,
                    'code' => $t->tracking_code,
                    'reason' => $reason,
                    'reason_label' => __('monitor.reasons.'.$reason),
                    'minutes' => $minutes,
                    'minutes_label' => Ui::minutes($minutes),
                    'minutes_in_hours' => $minutes !== null && abs($minutes) >= 100,
                    'urgency' => Ui::urgency($minutes),
                    'recipient' => $t->destination_name,
                    'address' => Str::limit((string) $t->destination_address, 44),
                    'status' => $t->status->label(),
                    'status_category' => $t->status_category_slug->value,
                    'status_form' => Ui::statusStyle($t->status_category_slug)['form'],
                    'status_ink' => Ui::statusStyle($t->status_category_slug)['ink'],
                    'courier' => $t->courier?->name,
                    // Links the exception to its line on the map; null when nobody holds it.
                    'courier_ink' => $t->courier ? Ui::courierInk($t->courier) : null,
                    'url' => route('tasks.show', $t),
                ];
            })
            ->all();
    }

    private function counts(?int $hubId, Carbon $dayStart, Carbon $dayEnd): array
    {
        $base = fn () => Task::query()->when($hubId, fn ($q) => $q->where('hub_id', $hubId));

        $finishedToday = $base()->inCategory(StatusCategory::Completed, StatusCategory::Failed, StatusCategory::Cancelled)
            ->whereBetween('ended_at', [$dayStart, $dayEnd]);

        $completed = (clone $finishedToday)->inCategory(StatusCategory::Completed)->count();
        $onTime = (clone $finishedToday)->inCategory(StatusCategory::Completed)->where('was_delayed', false)->count();

        return [
            'late' => $base()->open()->where('ends_at', '<', now())->count(),
            'unassigned' => $base()->inCategory(StatusCategory::Created, StatusCategory::Pool)->count(),
            'unresolved' => $base()->open()->where('is_destination_resolved', false)->count(),
            'in_motion' => $base()->active()->count(),
            'completed' => $completed,
            'failed' => (clone $finishedToday)->inCategory(StatusCategory::Failed)->count(),
            'on_time_rate' => $completed > 0 ? (int) round($onTime / $completed * 100) : null,
        ];
    }

    /** @return array{0: Carbon, 1: Carbon} today in the company's timezone, as UTC bounds */
    private function today(): array
    {
        $tz = $this->tenant->get()?->timezone ?? config('app.timezone');

        return [now($tz)->startOfDay()->utc(), now($tz)->endOfDay()->utc()];
    }

    private function inkFor(User $courier): string
    {
        return Ui::courierInk($courier);
    }

    private function initials(string $name): string
    {
        return collect(preg_split('/\s+/u', trim($name)))->take(2)->map(fn ($p) => mb_strtoupper(mb_substr($p, 0, 1)))->implode('');
    }
}
