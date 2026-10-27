@php
    $date = fn ($t) => $t ? $t->copy()->setTimezone('Asia/Baku')->translatedFormat('d M Y') : '—';
    $filtered = request()->hasAny(['q', 'plan', 'state']);
@endphp
<x-layouts::admin :title="__('admin.nav.companies')">
    <x-slot:actions>
        <a href="{{ route('admin.companies.create') }}" class="btn btn-primary"><x-icon name="plus" :size="16" />{{ __('admin.companies.new') }}</a>
    </x-slot:actions>

    <form method="GET" class="mb-5 flex flex-wrap gap-3">
        <div class="relative min-w-[240px] flex-1 sm:max-w-[360px]">
            <x-icon name="search" :size="16" class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-[var(--text-3)]" />
            <input name="q" value="{{ request('q') }}" class="input pl-9" placeholder="{{ __('admin.companies.search') }}" aria-label="{{ __('admin.companies.search') }}">
        </div>
        <select name="plan" class="input w-[180px]" onchange="this.form.submit()" aria-label="{{ __('admin.fields.plan') }}">
            <option value="">{{ __('admin.companies.all_plans') }}</option>
            @foreach ($plans as $slug => $plan)
                <option value="{{ $slug }}" @selected(request('plan') === $slug)>{{ $plan['name'] }}</option>
            @endforeach
        </select>
        <select name="state" class="input w-[180px]" onchange="this.form.submit()" aria-label="{{ __('admin.fields.state') }}">
            <option value="">{{ __('admin.companies.all_states') }}</option>
            @foreach (['active', 'trial', 'expired', 'suspended'] as $state)
                <option value="{{ $state }}" @selected(request('state') === $state)>{{ __('admin.states.'.$state) }}</option>
            @endforeach
        </select>
        <button class="btn"><x-icon name="filter" :size="16" />{{ __('Filter') }}</button>
        @if ($filtered)<a href="{{ route('admin.companies.index') }}" class="btn btn-ghost">{{ __('Reset') }}</a>@endif
    </form>

    <div class="plate overflow-hidden">
        @if ($companies->isEmpty())
            <x-empty icon="building-2" :title="$filtered ? __('admin.companies.no_match') : __('admin.companies.empty')" :action="$filtered ? null : route('admin.companies.create')" :action-label="__('admin.companies.new')" />
        @else
            <div class="overflow-x-auto">
                <table class="tbl min-w-[900px]">
                    <thead><tr>
                        <th>{{ __('admin.fields.company') }}</th><th>{{ __('admin.fields.plan') }}</th><th>{{ __('admin.fields.state') }}</th>
                        <th>{{ __('admin.fields.access_until') }}</th><th class="text-right">{{ __('admin.fields.staff') }}</th>
                        <th class="text-right">{{ __('admin.fields.couriers') }}</th><th class="text-right">{{ __('admin.fields.tasks_30') }}</th><th></th>
                    </tr></thead>
                    <tbody>
                    @foreach ($companies as $company)
                        <tr>
                            <td><a href="{{ route('admin.companies.show', $company) }}" class="block font-medium hover:underline">{{ $company->name }}</a>
                                <span class="text-[13px] text-[var(--text-3)]">#{{ $company->id }} · {{ $date($company->created_at) }}</span></td>
                            <td>{{ $company->plan()['name'] }}</td>
                            <td><x-admin.state :company="$company" /></td>
                            <td class="text-[var(--text-2)]">{{ $date($company->onTrial() ? $company->trial_ends_at : $company->subscription_ends_at) }}</td>
                            <td class="text-right">{{ $company->staff_count }}</td>
                            <td class="text-right">{{ $company->couriers_count }}</td>
                            <td class="text-right">{{ number_format($company->tasks_30_count, 0, ',', ' ') }}</td>
                            <td class="text-right"><a href="{{ route('admin.companies.show', $company) }}" class="btn btn-sm">{{ __('admin.open') }}</a></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            <div class="border-t border-[var(--hairline)] px-4 py-3">{{ $companies->links() }}</div>
        @endif
    </div>
</x-layouts::admin>
