<?php

namespace App\Http\Controllers\Api\Integration;

use App\Http\Controllers\Controller;
use App\Http\Requests\CourierRequest;
use App\Http\Resources\CourierResource;
use App\Models\User;
use App\Services\CourierService;
use App\Support\Phone;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class CourierController extends Controller
{
    public function __construct(private readonly CourierService $couriers) {}

    public function index(Request $request)
    {
        $f = (array) $request->query('filter', []);

        $couriers = User::query()
            ->couriers()
            ->with(['courierDetail', 'courierLocation', 'teams'])
            ->withCount([
                'courierTasks as current_tasks_count' => fn (Builder $q) => $q->active(),
                'courierTasks as finished_tasks_count' => fn (Builder $q) => $q->inCategory('completed', 'failed'),
            ])
            ->when($f['name'] ?? null, fn ($q, $v) => $q->where('name', 'ilike', '%'.$v.'%'))
            ->when($f['phone'] ?? null, fn ($q, $v) => $q->where('phone', Phone::normalize($v) ?? $v))
            ->when($f['team_id'] ?? null, fn ($q, $v) => $q->whereHas('teams', fn ($t) => $t->whereKey($v)))
            ->when($f['team_integration_id'] ?? null, fn ($q, $v) => $q->whereHas('teams', fn ($t) => $t->where('integration_id', $v)))
            ->when($f['hub_id'] ?? null, fn ($q, $v) => $q->whereHas('teams', fn ($t) => $t->where('hub_id', $v)))
            ->when($f['hub_integration_id'] ?? null, fn ($q, $v) => $q->whereHas('teams.hub', fn ($h) => $h->where('integration_id', $v)))
            ->when(isset($f['is_online']), fn ($q) => $q->whereHas('courierLocation', fn ($l) => $l->where('is_online', filter_var($f['is_online'], FILTER_VALIDATE_BOOL))))
            ->orderBy('name')
            ->simplePaginate(50);

        return CourierResource::collection($couriers);
    }

    public function show(User $courier)
    {
        return new CourierResource($courier->load(['courierDetail', 'courierLocation', 'teams']));
    }

    public function update(CourierRequest $request, User $courier)
    {
        $this->couriers->update($courier, $request->validated());

        return new CourierResource($courier->load(['courierDetail', 'courierLocation', 'teams']));
    }
}
