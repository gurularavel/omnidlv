@php $money = fn ($v) => $v === null ? __('admin.plans.on_request') : rtrim(rtrim(number_format($v, 2, ',', ' '), '0'), ',').' ₼'; @endphp
<x-layouts::admin :title="__('admin.nav.plans')">
    <x-slot:actions>
        <a href="{{ route('admin.plans.create') }}" class="btn btn-primary"><x-icon name="plus" :size="16" />{{ __('admin.plans.new') }}</a>
    </x-slot:actions>

    <p class="mb-5 max-w-[80ch] text-[var(--text-2)]">{{ __('admin.plans.intro') }}</p>

    <div class="grid gap-6 md:grid-cols-2 xl:grid-cols-3">
        @foreach ($plans as $plan)
            <section @class(['plate flex flex-col', 'opacity-70' => ! $plan->is_active])>
                <div class="plate-head flex items-center justify-between gap-3">
                    <h2 class="t-label text-[var(--text)]">{{ $plan->name }} <span class="text-[var(--text-3)]">· {{ $plan->slug }}</span></h2>
                    @unless ($plan->is_active)<span class="tag">{{ __('admin.plans.hidden') }}</span>@endunless
                </div>
                <div class="flex flex-1 flex-col gap-4 p-5">
                    <div>
                        <p class="t-display">{{ $money($plan->price_monthly) }}</p>
                        <p class="text-[13px] text-[var(--text-3)]">{{ __('admin.plans.per_courier_month') }}@if ($plan->price_yearly !== null) · {{ __('admin.plans.yearly', ['price' => $money($plan->price_yearly)]) }}@endif</p>
                    </div>
                    @if ($plan->description)<p class="text-[var(--text-2)]">{{ $plan->description }}</p>@endif
                    <dl class="grid grid-cols-2 gap-3 text-[14px]">
                        <div><dt class="t-label">{{ __('admin.fields.history_days') }}</dt><dd>{{ $plan->history_days ? __('admin.plans.days', ['count' => $plan->history_days]) : __('admin.plans.unlimited') }}</dd></div>
                        <div><dt class="t-label">{{ __('admin.fields.max_couriers') }}</dt><dd>{{ $plan->max_couriers ?? __('admin.plans.unlimited') }}</dd></div>
                        <div><dt class="t-label">{{ __('admin.nav.companies') }}</dt><dd>{{ $usage[$plan->slug] ?? 0 }}</dd></div>
                    </dl>
                    <ul class="flex flex-wrap gap-1.5">
                        @foreach ($plan->features as $feature)
                            <li class="tag">{{ __('admin.features.'.$feature) }}</li>
                        @endforeach
                    </ul>
                    <div class="mt-auto flex gap-2 pt-2">
                        <a href="{{ route('admin.plans.edit', $plan) }}" class="btn btn-sm"><x-icon name="pencil" :size="14" />{{ __('admin.edit') }}</a>
                        @if (! ($usage[$plan->slug] ?? 0))
                            <form method="POST" action="{{ route('admin.plans.destroy', $plan) }}" x-data @submit="if (! window.confirm(@js(__('admin.plans.delete_confirm', ['name' => $plan->name])))) $event.preventDefault()">
                                @csrf @method('DELETE')
                                <button class="btn btn-sm btn-ghost"><x-icon name="trash-2" :size="14" />{{ __('admin.delete') }}</button>
                            </form>
                        @endif
                    </div>
                </div>
            </section>
        @endforeach
    </div>
</x-layouts::admin>
