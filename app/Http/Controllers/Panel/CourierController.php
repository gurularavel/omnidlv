<?php

namespace App\Http\Controllers\Panel;

use App\Enums\VehicleType;
use App\Http\Controllers\Controller;
use App\Http\Requests\CourierRequest;
use App\Models\Team;
use App\Models\User;
use App\Services\CourierService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class CourierController extends Controller
{
    public function __construct(private readonly CourierService $couriers) {}

    public function index(Request $request)
    {
        $couriers = User::query()->couriers()
            ->with(['courierDetail', 'courierLocation', 'teams:id,name,color'])
            ->withCount(['courierTasks as active_tasks_count' => fn (Builder $q) => $q->active()])
            ->when($request->string('q')->trim()->value(), fn ($q, $term) => $q->where(fn ($q) => $q
                ->where('name', 'ilike', "%{$term}%")->orWhere('phone', 'ilike', "%{$term}%")))
            ->when($request->input('state') === 'online', fn ($q) => $q->whereHas('courierLocation', fn ($l) => $l->where('is_online', true)))
            ->when($request->input('state') === 'working', fn ($q) => $q->whereHas('courierLocation', fn ($l) => $l->where('is_working', true)))
            ->orderBy('name')
            ->paginate(50)
            ->withQueryString();

        return view('panel.couriers.index', ['couriers' => $couriers]);
    }

    public function create()
    {
        return view('panel.couriers.form', $this->formData(new User));
    }

    public function store(CourierRequest $request)
    {
        $courier = $this->couriers->create($request->validated());

        return redirect()->route('couriers.index')->with('status', __(':name can now sign in to the courier app with :phone.', [
            'name' => $courier->name, 'phone' => $courier->phone,
        ]));
    }

    public function edit(User $courier)
    {
        return view('panel.couriers.form', $this->formData($courier->load(['courierDetail', 'teams:id'])));
    }

    public function update(CourierRequest $request, User $courier)
    {
        $this->couriers->update($courier, $request->validated());

        return redirect()->route('couriers.index')->with('status', __('Courier saved.'));
    }

    public function destroy(User $courier)
    {
        $this->couriers->deactivate($courier);

        return redirect()->route('couriers.index')->with('status', __(':name no longer works for the company.', ['name' => $courier->name]));
    }

    private function formData(User $courier): array
    {
        return [
            'courier' => $courier,
            'teams' => Team::query()->orderBy('name')->get(['id', 'name', 'color']),
            'vehicles' => collect(VehicleType::cases())->mapWithKeys(fn ($v) => [$v->value => $v->label()]),
        ];
    }
}
