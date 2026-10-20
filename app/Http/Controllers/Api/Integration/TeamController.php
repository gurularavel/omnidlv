<?php

namespace App\Http\Controllers\Api\Integration;

use App\Http\Controllers\Controller;
use App\Http\Requests\TeamRequest;
use App\Http\Resources\TeamResource;
use App\Models\Team;
use App\Services\TeamService;
use Illuminate\Http\Request;

class TeamController extends Controller
{
    public function __construct(private readonly TeamService $teams) {}

    public function index(Request $request)
    {
        $teams = Team::query()
            ->withCount('couriers')
            ->when($request->input('filter.hub_id', $request->input('hub_id')), fn ($q, $v) => $q->where('hub_id', $v))
            ->when($request->input('filter.integration_id'), fn ($q, $v) => $q->where('integration_id', $v))
            ->orderBy('id')
            ->simplePaginate(50);

        return TeamResource::collection($teams);
    }

    public function store(TeamRequest $request)
    {
        $team = $this->teams->save(new Team, $request->validated());

        return (new TeamResource($team->loadCount('couriers')))->response()->setStatusCode(201);
    }

    public function show(Team $team)
    {
        return new TeamResource($team->loadCount('couriers'));
    }

    public function update(TeamRequest $request, Team $team)
    {
        return new TeamResource($this->teams->save($team, $request->validated())->loadCount('couriers'));
    }

    public function destroy(Team $team)
    {
        $team->delete();

        return response()->json(['id' => $team->id, 'deleted' => true]);
    }
}
