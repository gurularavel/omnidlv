<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Plan;
use App\Support\Plans;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PlanController extends Controller
{
    public function index()
    {
        $plans = Plan::query()->orderBy('sort')->orderBy('id')->get();
        $usage = Company::query()->selectRaw('plan_slug, count(*) as total')->groupBy('plan_slug')->pluck('total', 'plan_slug');

        return view('admin.plans.index', ['plans' => $plans, 'usage' => $usage]);
    }

    public function create()
    {
        return view('admin.plans.form', ['plan' => new Plan(['is_active' => true, 'sort' => (int) Plan::query()->max('sort') + 1])]);
    }

    public function store(Request $request)
    {
        $plan = Plan::create($this->validated($request));

        return redirect()->route('admin.plans.index')->with('status', __('admin.plans.created', ['name' => $plan->name]));
    }

    public function edit(Plan $plan)
    {
        return view('admin.plans.form', ['plan' => $plan]);
    }

    public function update(Request $request, Plan $plan)
    {
        $plan->update($this->validated($request, $plan));

        return redirect()->route('admin.plans.index')->with('status', __('admin.saved'));
    }

    public function destroy(Plan $plan)
    {
        if ($plan->companiesCount() > 0) {
            return back()->with('error', __('admin.plans.in_use'));
        }

        $plan->delete();

        return redirect()->route('admin.plans.index')->with('status', __('admin.plans.deleted', ['name' => $plan->name]));
    }

    private function validated(Request $request, ?Plan $plan = null): array
    {
        $data = $request->validate([
            // The slug is how companies refer to a plan, so it is fixed once the plan exists.
            'slug' => $plan ? ['prohibited'] : ['required', 'string', 'max:32', 'regex:/^[a-z0-9_-]+$/', Rule::unique('plans', 'slug')],
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:1000'],
            'price_monthly' => ['nullable', 'numeric', 'min:0', 'max:99999'],
            'price_yearly' => ['nullable', 'numeric', 'min:0', 'max:99999'],
            'history_days' => ['nullable', 'integer', 'min:1', 'max:36500'],
            'max_couriers' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'features' => ['array'],
            'features.*' => [Rule::in(Plans::FEATURES)],
            'sort' => ['required', 'integer', 'min:0', 'max:1000'],
        ]);

        return [
            ...$data,
            'features' => array_values(array_intersect(Plans::FEATURES, $data['features'] ?? [])),
            'is_active' => $request->boolean('is_active'),
        ];
    }
}
