@php
    $ink = old('color', $team->color ?? \App\Support\Ui::INKS[0]);
    $members = old('courier_ids', $team->exists ? $team->couriers->pluck('id')->all() : []);
@endphp
<x-layouts::app :title="$team->exists ? $team->name : __('New team')">
    <form method="POST" action="{{ $team->exists ? route('teams.update', $team) : route('teams.store') }}"
          class="mx-auto grid max-w-[1200px] gap-6 lg:grid-cols-[380px_minmax(0,1fr)]">
        @csrf
        @if ($team->exists) @method('PUT') @endif

        <section class="plate self-start">
            <div class="plate-head"><h2 class="t-label text-[var(--text)]">{{ __('Team') }}</h2></div>
            <div class="flex flex-col gap-5 p-5">
                <x-field :label="__('Name')" name="name" required><x-input name="name" :value="$team->name" required /></x-field>
                <x-field :label="__('Hub')" name="hub_id"><x-select name="hub_id" :options="$hubs->pluck('name', 'id')" :value="$team->hub_id" :placeholder="__('All hubs')" /></x-field>
                <fieldset>
                    <legend class="field-label t-label">{{ __('Line colour') }}</legend>
                    <div class="flex gap-2">
                        @foreach (\App\Support\Ui::INKS as $color)
                            <label class="relative">
                                <input type="radio" name="color" value="{{ $color }}" class="peer sr-only" @checked($ink === $color)>
                                <span class="block h-8 w-12 cursor-pointer rounded-md border-2 border-transparent peer-checked:border-[var(--text)] peer-focus-visible:outline peer-focus-visible:outline-2 peer-focus-visible:outline-[var(--focus)]" style="background: {{ $color }}"></span>
                                <span class="sr-only">{{ $color }}</span>
                            </label>
                        @endforeach
                    </div>
                </fieldset>
                <x-field :label="__('Your code')" name="integration_id"><x-input name="integration_id" :value="$team->integration_id" /></x-field>
                <fieldset>
                    <legend class="field-label t-label">{{ __('Couriers') }}</legend>
                    @if ($couriers->isEmpty())
                        <p class="text-[13px] text-[var(--text-3)]">{{ __('No couriers yet.') }} <a href="{{ route('couriers.create') }}" class="underline">{{ __('Add a courier') }}</a></p>
                    @else
                        <input type="hidden" name="courier_ids" value="">
                        <div class="flex max-h-[240px] flex-col gap-2 overflow-y-auto pr-1">
                            @foreach ($couriers as $courier)
                                <label class="flex items-center gap-3">
                                    <input type="checkbox" class="check" name="courier_ids[]" value="{{ $courier->id }}" @checked(in_array($courier->id, $members))>
                                    {{ $courier->name }}
                                </label>
                            @endforeach
                        </div>
                    @endif
                </fieldset>
                <div class="flex gap-3">
                    <button class="btn btn-primary flex-1">{{ __('Save team') }}</button>
                    <a href="{{ route('teams.index') }}" class="btn">{{ __('Cancel') }}</a>
                </div>
            </div>
        </section>

        <section class="plate overflow-hidden" x-data="polygonEditor(@js(old('polygon') ? json_decode(old('polygon'), true) : $team->polygon), @js($ink), @js($hubs->map->only(['name', 'lat', 'lng'])->values()))">
            <div class="plate-head">
                <h2 class="t-label text-[var(--text)]">{{ __('Service zone') }}</h2>
                <span class="ml-auto text-[13px] text-[var(--text-3)]">{{ __('Draw the polygon with the tool at the top right. Orders inside it go to this team.') }}</span>
            </div>
            <input type="hidden" name="polygon" :value="value">
            <div x-ref="map" class="h-[560px]"></div>
            @error('polygon')<p class="field-error px-5 pb-4">{{ $message }}</p>@enderror
        </section>
    </form>

    @if ($team->exists)
        <form method="POST" action="{{ route('teams.destroy', $team) }}" class="mx-auto mt-6 max-w-[1200px]" onsubmit="return confirm(@js(__('Remove this team? Couriers stay, only the grouping goes.')))">
            @csrf @method('DELETE')
            <button class="btn btn-danger"><x-icon name="trash-2" :size="16" />{{ __('Remove team') }}</button>
        </form>
    @endif
</x-layouts::app>
