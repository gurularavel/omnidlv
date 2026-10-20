<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Http\Requests\TeamRequest;
use App\Models\Hub;
use App\Models\Team;
use App\Models\User;
use App\Services\TeamService;

class TeamController extends Controller
{
    public function __construct(private readonly TeamService $teams) {}

    public function index()
    {
        return view('panel.teams.index', [
            'teams' => Team::query()->with('hub:id,name')->withCount('couriers')->orderBy('name')->get(),
        ]);
    }

    public function create()
    {
        return view('panel.teams.form', $this->formData(new Team));
    }

    public function store(TeamRequest $request)
    {
        $team = $this->teams->save(new Team, $request->validated());

        return redirect()->route('teams.index')->with('status', __('Team :name added.', ['name' => $team->name]));
    }

    public function edit(Team $team)
    {
        return view('panel.teams.form', $this->formData($team->load('couriers:id')));
    }

    public function update(TeamRequest $request, Team $team)
    {
        $this->teams->save($team, $request->validated());

        return redirect()->route('teams.index')->with('status', __('Team saved.'));
    }

    public function destroy(Team $team)
    {
        $team->delete();

        return redirect()->route('teams.index')->with('status', __('Team removed.'));
    }

    private function formData(Team $team): array
    {
        return [
            'team' => $team,
            'hubs' => Hub::query()->orderBy('name')->get(['id', 'name', 'lat', 'lng']),
            'couriers' => User::query()->couriers()->orderBy('name')->get(['users.id', 'users.name']),
        ];
    }
}
