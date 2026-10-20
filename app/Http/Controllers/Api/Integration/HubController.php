<?php

namespace App\Http\Controllers\Api\Integration;

use App\Http\Controllers\Controller;
use App\Http\Requests\HubRequest;
use App\Http\Resources\HubResource;
use App\Models\Hub;
use App\Services\HubService;
use Illuminate\Http\Request;

class HubController extends Controller
{
    public function __construct(private readonly HubService $hubs) {}

    public function index(Request $request)
    {
        $hubs = Hub::query()
            ->withCount('teams')
            ->with(['teams' => fn ($q) => $q->withCount('couriers')])
            ->when($request->input('filter.integration_id'), fn ($q, $v) => $q->where('integration_id', $v))
            ->orderBy('id')
            ->simplePaginate(50);

        return HubResource::collection($hubs);
    }

    public function store(HubRequest $request)
    {
        $hub = $this->hubs->save(new Hub, $request->validated());

        return (new HubResource($hub->loadCount('teams')))->response()->setStatusCode(201);
    }

    public function show(Hub $hub)
    {
        return new HubResource($hub->loadCount('teams')->load(['teams' => fn ($q) => $q->withCount('couriers')]));
    }

    public function update(HubRequest $request, Hub $hub)
    {
        return new HubResource($this->hubs->save($hub, $request->validated())->loadCount('teams'));
    }

    public function destroy(Hub $hub)
    {
        $this->hubs->delete($hub);

        return response()->json(['id' => $hub->id, 'deleted' => true]);
    }
}
