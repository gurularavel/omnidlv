@php
    $date = fn ($t) => $t ? $t->copy()->setTimezone('Asia/Baku')->translatedFormat('d M Y H:i') : '—';
    $filtered = request()->hasAny(['q', 'kind']);
@endphp
<x-layouts::admin :title="__('admin.nav.users')">
    <form method="GET" class="mb-5 flex flex-wrap gap-3">
        <div class="relative min-w-[240px] flex-1 sm:max-w-[360px]">
            <x-icon name="search" :size="16" class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-[var(--text-3)]" />
            <input name="q" value="{{ request('q') }}" class="input pl-9" placeholder="{{ __('admin.users.search') }}" aria-label="{{ __('admin.users.search') }}">
        </div>
        <select name="kind" class="input w-[200px]" onchange="this.form.submit()" aria-label="{{ __('admin.fields.role') }}">
            <option value="">{{ __('admin.users.everyone') }}</option>
            @foreach (['staff', 'courier', 'super'] as $kind)
                <option value="{{ $kind }}" @selected(request('kind') === $kind)>{{ __('admin.users.kinds.'.$kind) }}</option>
            @endforeach
        </select>
        <button class="btn"><x-icon name="filter" :size="16" />{{ __('Filter') }}</button>
        @if ($filtered)<a href="{{ route('admin.users.index') }}" class="btn btn-ghost">{{ __('Reset') }}</a>@endif
    </form>

    <div class="plate overflow-hidden">
        @if ($users->isEmpty())
            <x-empty icon="users" :title="__('admin.users.no_match')" />
        @else
            <div class="overflow-x-auto">
                <table class="tbl min-w-[900px]">
                    <thead><tr><th>{{ __('admin.fields.person_name') }}</th><th>{{ __('admin.users.companies') }}</th><th>{{ __('admin.fields.last_login') }}</th><th></th></tr></thead>
                    <tbody>
                    @foreach ($users as $user)
                        @php $canEnter = ! $user->is_super && $user->employments->contains(fn ($e) => $e->is_active && $e->role_slug !== 'courier'); @endphp
                        <tr>
                            <td>
                                <span class="block font-medium">{{ $user->name }}@if ($user->is_super) <span class="tag ml-1 text-ink-amber">{{ __('admin.superadmin') }}</span>@endif</span>
                                <span class="text-[13px] text-[var(--text-2)]">{{ collect([$user->email, $user->phone])->filter()->join(' · ') ?: '—' }}</span>
                            </td>
                            <td>
                                <span class="flex flex-col gap-1">
                                    @forelse ($user->employments as $employment)
                                        <span @class(['text-[13px]', 'opacity-60' => ! $employment->is_active])>
                                            @if ($employment->company)<a href="{{ route('admin.companies.show', $employment->company_id) }}" class="hover:underline">{{ $employment->company->name }}</a>@else #{{ $employment->company_id }} @endif
                                            <span class="text-[var(--text-3)]">· {{ $employment->role()->label() }}</span>
                                        </span>
                                    @empty
                                        <span class="text-[var(--text-3)]">—</span>
                                    @endforelse
                                </span>
                            </td>
                            <td class="text-[var(--text-2)]">{{ $date($user->last_login_at) }}</td>
                            <td>
                                <span class="flex justify-end gap-2">
                                    @if ($canEnter)
                                        <form method="POST" action="{{ route('admin.users.impersonate', $user) }}">@csrf
                                            <button class="btn btn-sm" title="{{ __('admin.users.impersonate_hint') }}"><x-icon name="log-in" :size="14" />{{ __('admin.users.impersonate') }}</button></form>
                                    @endif
                                    @if ($user->email)
                                        <form method="POST" action="{{ route('admin.users.password', $user) }}" x-data @submit="if (! window.confirm(@js(__('admin.users.password_confirm', ['name' => $user->name])))) $event.preventDefault()">@csrf
                                            <button class="btn btn-sm btn-ghost"><x-icon name="key-round" :size="14" />{{ __('admin.users.reset_password') }}</button></form>
                                    @endif
                                </span>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            <div class="border-t border-[var(--hairline)] px-4 py-3">{{ $users->links() }}</div>
        @endif
    </div>
</x-layouts::admin>
