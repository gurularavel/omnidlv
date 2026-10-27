<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Middleware\SetLocale;
use App\Models\Company;
use App\Models\Employment;
use App\Models\Task;
use App\Models\User;
use App\Rules\PhoneFormat;
use App\Services\Admin\CompanyProvisioner;
use App\Support\Phone;
use App\Support\Plans;
use DateTimeZone;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class CompanyController extends Controller
{
    public function __construct(private readonly CompanyProvisioner $provisioner) {}

    public function index(Request $request)
    {
        $state = $request->input('state');

        $companies = Company::query()
            ->withCount([
                'employments as couriers_count' => fn (Builder $q) => $q->where('role_slug', Role::Courier->value)->where('is_active', true),
                'employments as staff_count' => fn (Builder $q) => $q->where('role_slug', '<>', Role::Courier->value)->where('is_active', true),
                'tasks as tasks_30_count' => fn (Builder $q) => $q->where('created_at', '>=', now()->subDays(30)),
            ])
            ->when($request->string('q')->trim()->value(), fn ($q, $term) => $q->where(fn ($q) => $q
                ->where('name', 'ilike', "%{$term}%")->orWhere('slug', 'ilike', "%{$term}%")))
            ->when($request->input('plan'), fn ($q, $plan) => $q->where('plan_slug', $plan))
            ->when($state === 'suspended', fn ($q) => $q->whereNotNull('suspended_at'))
            ->when($state === 'trial', fn ($q) => $q->whereNull('suspended_at')->where('trial_ends_at', '>', now()))
            ->when($state === 'active', fn ($q) => $q->whereNull('suspended_at')
                ->where(fn ($q) => $q->whereNull('trial_ends_at')->orWhere('trial_ends_at', '<=', now()))
                ->where('subscription_ends_at', '>', now()))
            ->when($state === 'expired', fn ($q) => $q->whereNull('suspended_at')
                ->where(fn ($q) => $q->whereNull('trial_ends_at')->orWhere('trial_ends_at', '<=', now()))
                ->where(fn ($q) => $q->whereNull('subscription_ends_at')->orWhere('subscription_ends_at', '<=', now())))
            ->latest('id')
            ->paginate(30)
            ->withQueryString();

        return view('admin.companies.index', ['companies' => $companies, 'plans' => Plans::all()]);
    }

    public function create()
    {
        return view('admin.companies.create', $this->formOptions());
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            ...$this->companyRules(),
            'owner_name' => ['required', 'string', 'max:191'],
            'owner_email' => ['required', 'email', 'max:191'],
            'owner_phone' => ['nullable', 'string', 'max:32', new PhoneFormat],
            'owner_password' => ['nullable', Password::min(8)],
        ]);
        $this->ensurePhoneFree($data['owner_phone'] ?? null, 'owner_phone');

        [$company, $owner, $password] = $this->provisioner->create(
            $this->companyData($data),
            ['name' => $data['owner_name'], 'email' => $data['owner_email'], 'phone' => $data['owner_phone'] ?? null, 'password' => $data['owner_password'] ?? null],
        );

        return redirect()->route('admin.companies.show', $company)
            ->with('status', __('admin.companies.created', ['name' => $company->name]))
            ->with('credentials', $password ? ['email' => $owner->email, 'password' => $password] : null);
    }

    public function show(Company $company)
    {
        $employments = Employment::query()->where('company_id', $company->id)
            ->with('user:id,name,email,phone,last_login_at,is_super')
            ->orderByRaw("role_slug = 'courier'")->orderBy('role_slug')->orderBy('id')
            ->get();

        $tasks = Task::query()->where('company_id', $company->id)
            ->selectRaw('count(*) as total, count(*) filter (where created_at >= ?) as last_30, max(created_at) as last_at', [now()->subDays(30)])
            ->first();

        return view('admin.companies.show', [
            'company' => $company,
            'staff' => $employments->where('role_slug', '<>', Role::Courier->value)->values(),
            'couriers' => $employments->where('role_slug', Role::Courier->value)->values(),
            'tasks' => $tasks,
            'roles' => collect([Role::Owner, Role::Admin, Role::Dispatcher])->mapWithKeys(fn (Role $r) => [$r->value => $r->label()]),
            ...$this->formOptions($company),
        ]);
    }

    public function update(Request $request, Company $company)
    {
        $data = $request->validate($this->companyRules($company));

        $company->update($this->companyData($data));

        return back()->with('status', __('admin.saved'));
    }

    public function suspend(Company $company)
    {
        $company->update(['suspended_at' => now()]);

        return back()->with('status', __('admin.companies.suspended', ['name' => $company->name]));
    }

    public function resume(Company $company)
    {
        $company->update(['suspended_at' => null]);

        return back()->with('status', __('admin.companies.resumed', ['name' => $company->name]));
    }

    /** Adds an owner, admin or dispatcher (couriers are added by the company with their phone). */
    public function storeStaff(Request $request, Company $company)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:191'],
            'email' => ['required', 'email', 'max:191'],
            'phone' => ['nullable', 'string', 'max:32', new PhoneFormat],
            'role' => ['required', Rule::in([Role::Owner->value, Role::Admin->value, Role::Dispatcher->value])],
        ]);
        $this->ensurePhoneFree($data['phone'] ?? null, 'phone');

        [$user, $password] = $this->provisioner->addStaff($company, $data);

        return back()
            ->with('status', __('admin.staff.added', ['name' => $user->name]))
            ->with('credentials', $password ? ['email' => $user->email, 'password' => $password] : null);
    }

    public function toggleEmployment(Employment $employment)
    {
        $employment->update(['is_active' => ! $employment->is_active]);

        return back()->with('status', __('admin.saved'));
    }

    private function companyRules(?Company $company = null): array
    {
        $plans = Plans::all();
        // An inactive plan can stay on the company that already has it, but is not offered to others.
        $allowed = array_keys(array_filter($plans, fn ($p) => $p['is_active'] || $p['slug'] === $company?->plan_slug));

        return [
            'name' => ['required', 'string', 'max:191'],
            'short_name' => ['nullable', 'string', 'max:32'],
            'plan_slug' => ['required', Rule::in($allowed)],
            'timezone' => ['required', 'timezone:all'],
            'locale' => ['required', Rule::in(SetLocale::LOCALES)],
            'trial_ends_at' => ['nullable', 'date'],
            'subscription_ends_at' => ['nullable', 'date'],
            'admin_note' => ['nullable', 'string', 'max:2000'],
        ];
    }

    private function companyData(array $data): array
    {
        // Dates are entered as days; access lasts to the end of that day in the company's timezone.
        $endOfDay = fn (?string $date) => $date ? Carbon::parse($date, $data['timezone'])->endOfDay()->utc() : null;

        return [
            'name' => $data['name'],
            'short_name' => $data['short_name'] ?? null,
            'plan_slug' => $data['plan_slug'],
            'timezone' => $data['timezone'],
            'locale' => $data['locale'],
            'trial_ends_at' => $endOfDay($data['trial_ends_at'] ?? null),
            'subscription_ends_at' => $endOfDay($data['subscription_ends_at'] ?? null),
            'admin_note' => $data['admin_note'] ?? null,
        ];
    }

    private function formOptions(?Company $company = null): array
    {
        return [
            'plans' => collect(Plans::all())
                ->filter(fn ($p) => $p['is_active'] || $p['slug'] === $company?->plan_slug)
                ->mapWithKeys(fn ($p) => [$p['slug'] => $p['name']]),
            'timezones' => array_combine($zones = DateTimeZone::listIdentifiers(), $zones),
            'locales' => ['az' => 'Azərbaycan', 'en' => 'English', 'ru' => 'Русский', 'tr' => 'Türkçe'],
            'trialDays' => Plans::TRIAL_DAYS,
        ];
    }

    private function ensurePhoneFree(?string $phone, string $field): void
    {
        if (! $phone) {
            return;
        }

        $normalized = Phone::normalize($phone, 'AZ') ?? throw ValidationException::withMessages([$field => __('validation.phone')]);

        if (User::query()->where('phone', $normalized)->exists()) {
            throw ValidationException::withMessages([$field => __('validation.unique', ['attribute' => __('Phone')])]);
        }
    }
}
