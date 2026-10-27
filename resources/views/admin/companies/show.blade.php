@php
    $date = fn ($t, $f = 'd M Y') => $t ? \Illuminate\Support\Carbon::parse($t)->setTimezone('Asia/Baku')->translatedFormat($f) : '—';
    $plan = $company->plan();
@endphp
<x-layouts::admin :title="$company->name">
    <x-slot:actions>
        @if ($company->isSuspended())
            <form method="POST" action="{{ route('admin.companies.resume', $company) }}">@csrf
                <button class="btn"><x-icon name="lock-open" :size="16" />{{ __('admin.companies.resume') }}</button></form>
        @else
            <form method="POST" action="{{ route('admin.companies.suspend', $company) }}" x-data @submit="if (! window.confirm(@js(__('admin.companies.suspend_confirm', ['name' => $company->name])))) $event.preventDefault()">@csrf
                <button class="btn btn-danger"><x-icon name="lock" :size="16" />{{ __('admin.companies.suspend') }}</button></form>
        @endif
    </x-slot:actions>

    <section class="plate mb-6 grid grid-cols-2 gap-px overflow-hidden bg-[var(--hairline)] sm:grid-cols-4" aria-label="{{ __('admin.nav.overview') }}">
        <div class="flex flex-col gap-2 bg-[var(--surface-raised)] px-5 py-4">
            <span class="t-label">{{ __('admin.fields.state') }}</span>
            <span><x-admin.state :company="$company" /></span>
            <span class="text-[13px] text-[var(--text-3)]">{{ $plan['name'] }} · {{ __('admin.fields.access_until') }} {{ $date($company->onTrial() ? $company->trial_ends_at : $company->subscription_ends_at) }}</span>
        </div>
        <div class="flex flex-col gap-2 bg-[var(--surface-raised)] px-5 py-4">
            <span class="t-label">{{ __('admin.fields.couriers') }}</span>
            <span class="t-display">{{ $couriers->where('is_active', true)->count() }}@if ($plan['max_couriers'])<span class="text-[var(--text-3)]"> / {{ $plan['max_couriers'] }}</span>@endif</span>
        </div>
        <div class="flex flex-col gap-2 bg-[var(--surface-raised)] px-5 py-4">
            <span class="t-label">{{ __('admin.fields.tasks_30') }}</span>
            <span class="t-display">{{ number_format($tasks->last_30, 0, ',', ' ') }}</span>
            <span class="text-[13px] text-[var(--text-3)]">{{ __('admin.companies.tasks_total', ['count' => number_format($tasks->total, 0, ',', ' ')]) }}</span>
        </div>
        <div class="flex flex-col gap-2 bg-[var(--surface-raised)] px-5 py-4">
            <span class="t-label">{{ __('admin.companies.last_task') }}</span>
            <span class="t-value">{{ $date($tasks->last_at, 'd M Y H:i') }}</span>
            <span class="text-[13px] text-[var(--text-3)]">#{{ $company->id }} · {{ $company->slug }}</span>
        </div>
    </section>

    <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_420px]">
        <div class="flex flex-col gap-6">
            <section class="plate overflow-hidden">
                <div class="plate-head"><h2 class="t-label text-[var(--text)]">{{ __('admin.staff.title') }}</h2></div>
                @if ($staff->isEmpty())
                    <p class="px-5 py-4 text-[var(--text-2)]">{{ __('admin.staff.empty') }}</p>
                @else
                    <div class="overflow-x-auto">
                        <table class="tbl min-w-[640px]">
                            <thead><tr><th>{{ __('admin.fields.person_name') }}</th><th>{{ __('admin.fields.role') }}</th><th>{{ __('admin.fields.last_login') }}</th><th></th></tr></thead>
                            <tbody>
                            @foreach ($staff as $employment)
                                @php $user = $employment->user; @endphp
                                <tr @class(['opacity-60' => ! $employment->is_active])>
                                    <td><span class="block font-medium">{{ $user->name }}</span><span class="text-[13px] text-[var(--text-2)]">{{ $user->email }}</span></td>
                                    <td>{{ $employment->role()->label() }}@unless ($employment->is_active)<span class="block text-[13px] text-[var(--text-3)]">{{ __('admin.staff.inactive') }}</span>@endunless</td>
                                    <td class="text-[var(--text-2)]">{{ $date($user->last_login_at, 'd M Y H:i') }}</td>
                                    <td>
                                        <span class="flex justify-end gap-2">
                                            @if ($employment->is_active && ! $user->is_super)
                                                <form method="POST" action="{{ route('admin.users.impersonate', [$user, 'company' => $company->id]) }}">@csrf
                                                    <button class="btn btn-sm" title="{{ __('admin.users.impersonate_hint') }}"><x-icon name="log-in" :size="14" />{{ __('admin.users.impersonate') }}</button></form>
                                            @endif
                                            <form method="POST" action="{{ route('admin.users.password', $user) }}" x-data @submit="if (! window.confirm(@js(__('admin.users.password_confirm', ['name' => $user->name])))) $event.preventDefault()">@csrf
                                                <button class="btn btn-sm btn-ghost"><x-icon name="key-round" :size="14" />{{ __('admin.users.reset_password') }}</button></form>
                                            <form method="POST" action="{{ route('admin.employments.toggle', $employment) }}">@csrf
                                                <button class="btn btn-sm btn-ghost">{{ $employment->is_active ? __('admin.staff.deactivate') : __('admin.staff.activate') }}</button></form>
                                        </span>
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif

                <form method="POST" action="{{ route('admin.companies.staff', $company) }}" class="grid gap-4 border-t border-[var(--hairline)] p-5 sm:grid-cols-2">
                    @csrf
                    <h3 class="t-label text-[var(--text)] sm:col-span-2">{{ __('admin.staff.add') }}</h3>
                    <x-field :label="__('admin.fields.person_name')" name="name" required><x-input name="name" required maxlength="191" /></x-field>
                    <x-field :label="__('admin.fields.email')" name="email" required><x-input name="email" type="email" required maxlength="191" /></x-field>
                    <x-field :label="__('admin.fields.phone')" name="phone"><x-input name="phone" type="tel" maxlength="32" /></x-field>
                    <x-field :label="__('admin.fields.role')" name="role"><x-select name="role" :options="$roles" value="admin" /></x-field>
                    <p class="text-[13px] text-[var(--text-3)] sm:col-span-2">{{ __('admin.staff.add_hint') }}</p>
                    <div class="sm:col-span-2"><button class="btn"><x-icon name="plus" :size="16" />{{ __('admin.staff.add') }}</button></div>
                </form>
            </section>

            <section class="plate overflow-hidden">
                <div class="plate-head"><h2 class="t-label text-[var(--text)]">{{ __('admin.fields.couriers') }}</h2></div>
                @if ($couriers->isEmpty())
                    <p class="px-5 py-4 text-[var(--text-2)]">{{ __('admin.companies.no_couriers') }}</p>
                @else
                    <div class="overflow-x-auto">
                        <table class="tbl min-w-[520px]">
                            <thead><tr><th>{{ __('admin.fields.person_name') }}</th><th>{{ __('admin.fields.phone') }}</th><th>{{ __('admin.fields.state') }}</th></tr></thead>
                            <tbody>
                            @foreach ($couriers as $employment)
                                <tr>
                                    <td class="font-medium">{{ $employment->user->name }}</td>
                                    <td class="text-[var(--text-2)]">{{ $employment->user->phone }}</td>
                                    <td class="text-[var(--text-2)]">{{ $employment->is_active ? __('admin.staff.active') : __('admin.staff.inactive') }}</td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </section>
        </div>

        <form method="POST" action="{{ route('admin.companies.update', $company) }}" class="plate self-start">
            @csrf @method('PUT')
            <div class="plate-head"><h2 class="t-label text-[var(--text)]">{{ __('admin.companies.settings') }}</h2></div>
            <div class="flex flex-col gap-5 p-5">
                @include('admin.companies._fields')
                <button class="btn btn-primary">{{ __('admin.save') }}</button>
            </div>
        </form>
    </div>
</x-layouts::admin>
