<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Http\Requests\HubRequest;
use App\Models\Hub;
use App\Services\HubService;

class HubController extends Controller
{
    public function __construct(private readonly HubService $hubs) {}

    public function index()
    {
        return view('panel.hubs.index', [
            'hubs' => Hub::query()->withCount(['teams', 'tasks as open_tasks_count' => fn ($q) => $q->open()])->orderBy('name')->get(),
        ]);
    }

    public function create()
    {
        return view('panel.hubs.form', ['hub' => new Hub]);
    }

    public function store(HubRequest $request)
    {
        $hub = $this->hubs->save(new Hub, $request->validated());

        return redirect()->route('hubs.index')->with('status', __('Hub :name added.', ['name' => $hub->name]));
    }

    public function edit(Hub $hub)
    {
        return view('panel.hubs.form', ['hub' => $hub]);
    }

    public function update(HubRequest $request, Hub $hub)
    {
        $this->hubs->save($hub, $request->validated());

        return redirect()->route('hubs.index')->with('status', __('Hub saved.'));
    }

    public function destroy(Hub $hub)
    {
        $this->hubs->delete($hub);

        return redirect()->route('hubs.index')->with('status', __('Hub removed.'));
    }
}
