@php
    $date = fn ($t) => $t ? $t->copy()->setTimezone('Asia/Baku')->translatedFormat('d M Y') : '—';
    $figures = [
        [__('admin.figures.companies'), $companyCount, __('admin.figures.companies_note', ['active' => $states['active'] ?? 0, 'trial' => $states['trial'] ?? 0])],
        [__('admin.figures.suspended'), ($states['suspended'] ?? 0) + ($states['expired'] ?? 0), __('admin.figures.suspended_note', ['suspended' => $states['suspended'] ?? 0, 'expired' => $states['expired'] ?? 0])],
        [__('admin.figures.couriers'), $couriers, __('admin.figures.staff_note', ['count' => $staff])],
        [__('admin.figures.tasks_today'), number_format($tasksToday, 0, ',', ' '), null],
        [__('admin.figures.tasks_30'), number_format($tasks30, 0, ',', ' '), __('admin.figures.completed_note', ['count' => number_format($completed30, 0, ',', ' ')])],
    ];
@endphp
<x-layouts::admin :title="__('admin.nav.overview')">
    <x-slot:actions>
        <a href="{{ route('admin.companies.create') }}" class="btn btn-primary"><x-icon name="plus" :size="16" />{{ __('admin.companies.new') }}</a>
    </x-slot:actions>

    <section class="plate mb-6 grid grid-cols-2 gap-px overflow-hidden bg-[var(--hairline)] sm:grid-cols-3 xl:grid-cols-5" aria-label="{{ __('admin.nav.overview') }}">
        @foreach ($figures as [$label, $value, $note])
            <div class="flex flex-col gap-2 bg-[var(--surface-raised)] px-5 py-4">
                <span class="t-label">{{ $label }}</span>
                <span class="t-display">{{ $value }}</span>
                @if ($note)<span class="text-[13px] text-[var(--text-3)]">{{ $note }}</span>@endif
            </div>
        @endforeach
    </section>

    <div class="grid gap-6 xl:grid-cols-2">
        <section class="plate overflow-hidden">
            <div class="plate-head"><h2 class="t-label text-[var(--text)]">{{ __('admin.dashboard.top') }}</h2></div>
            @if ($top->isEmpty())
                <x-empty icon="package" :title="__('admin.dashboard.no_tasks')" />
            @else
                <div class="overflow-x-auto">
                    <table class="tbl">
                        <thead><tr><th>{{ __('admin.fields.company') }}</th><th>{{ __('admin.fields.plan') }}</th><th class="text-right">{{ __('admin.fields.tasks_30') }}</th></tr></thead>
                        <tbody>
                        @foreach ($top as [$company, $count])
                            <tr>
                                <td><a href="{{ route('admin.companies.show', $company) }}" class="font-medium hover:underline">{{ $company->name }}</a></td>
                                <td class="text-[var(--text-2)]">{{ $company->plan()['name'] }}</td>
                                <td class="text-right">{{ number_format($count, 0, ',', ' ') }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>

        <div class="flex flex-col gap-6">
            <section class="plate overflow-hidden">
                <div class="plate-head"><h2 class="t-label text-[var(--text)]">{{ __('admin.dashboard.expiring') }}</h2></div>
                @if ($expiring->isEmpty())
                    <p class="px-5 py-4 text-[var(--text-2)]">{{ __('admin.dashboard.none_expiring') }}</p>
                @else
                    <table class="tbl">
                        <tbody>
                        @foreach ($expiring as $company)
                            <tr>
                                <td><a href="{{ route('admin.companies.show', $company) }}" class="font-medium hover:underline">{{ $company->name }}</a></td>
                                <td><x-admin.state :company="$company" /></td>
                                <td class="text-right text-[var(--text-2)]">{{ $date($company->onTrial() ? $company->trial_ends_at : $company->subscription_ends_at) }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                @endif
            </section>

            <section class="plate overflow-hidden">
                <div class="plate-head"><h2 class="t-label text-[var(--text)]">{{ __('admin.dashboard.latest') }}</h2></div>
                @if ($latest->isEmpty())
                    <x-empty icon="building-2" :title="__('admin.companies.empty')" :action="route('admin.companies.create')" :action-label="__('admin.companies.new')" />
                @else
                    <table class="tbl">
                        <tbody>
                        @foreach ($latest as $company)
                            <tr>
                                <td><a href="{{ route('admin.companies.show', $company) }}" class="font-medium hover:underline">{{ $company->name }}</a></td>
                                <td><x-admin.state :company="$company" /></td>
                                <td class="text-right text-[var(--text-2)]">{{ $date($company->created_at) }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                @endif
            </section>
        </div>
    </div>
</x-layouts::admin>
