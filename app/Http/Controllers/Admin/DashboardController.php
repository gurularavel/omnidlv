<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Role;
use App\Enums\StatusCategory;
use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Employment;
use App\Models\Task;

/** Platform overview. No tenant is set on /admin, so company-scoped models see every company. */
class DashboardController extends Controller
{
    public function __invoke()
    {
        $companies = Company::query()->get(['id', 'name', 'plan_slug', 'trial_ends_at', 'subscription_ends_at', 'suspended_at', 'created_at']);
        $since = now()->subDays(30);

        $tasks30 = Task::query()->where('created_at', '>=', $since)
            ->selectRaw('company_id, count(*) as total')
            ->selectRaw('count(*) filter (where status_category_slug = ?) as completed', [StatusCategory::Completed->value])
            ->groupBy('company_id')
            ->get()
            ->keyBy('company_id');

        $employees = Employment::query()->where('is_active', true)
            ->selectRaw('count(*) filter (where role_slug = ?) as couriers, count(*) filter (where role_slug <> ?) as staff', [Role::Courier->value, Role::Courier->value])
            ->first();

        return view('admin.dashboard', [
            'states' => $companies->countBy(fn (Company $c) => $c->state()),
            'companyCount' => $companies->count(),
            'couriers' => (int) $employees->couriers,
            'staff' => (int) $employees->staff,
            'tasksToday' => Task::query()->where('created_at', '>=', now()->startOfDay())->count(),
            'tasks30' => (int) $tasks30->sum('total'),
            'completed30' => (int) $tasks30->sum('completed'),
            'top' => $companies->map(fn (Company $c) => [$c, (int) ($tasks30[$c->id]->total ?? 0)])
                ->filter(fn ($row) => $row[1] > 0)->sortByDesc(fn ($row) => $row[1])->take(10)->values(),
            'latest' => $companies->sortByDesc('created_at')->take(6)->values(),
            'expiring' => $companies->filter(fn (Company $c) => ! $c->isSuspended()
                && ($end = $c->onTrial() ? $c->trial_ends_at : $c->subscription_ends_at)
                && $end->isFuture() && $end->lessThan(now()->addDays(7)))->values(),
        ]);
    }
}
