@php
    $detail = $courier->courierDetail;
    $teamIds = old('team_ids', $courier->exists ? $courier->teams->pluck('id')->all() : []);
@endphp
<x-layouts::app :title="$courier->exists ? $courier->name : __('New courier')">
    <form method="POST" action="{{ $courier->exists ? route('couriers.update', $courier) : route('couriers.store') }}" class="mx-auto grid max-w-[960px] gap-6 lg:grid-cols-2">
        @csrf
        @if ($courier->exists) @method('PUT') @endif

        <section class="plate">
            <div class="plate-head"><h2 class="t-label text-[var(--text)]">{{ __('Person') }}</h2></div>
            <div class="flex flex-col gap-5 p-5">
                <x-field :label="__('Full name')" name="name" required><x-input name="name" :value="$courier->name" required /></x-field>
                <x-field :label="__('Mobile phone')" name="phone" required :hint="__('The courier signs in with an SMS code sent to this number.')">
                    <x-input name="phone" type="tel" data-phone inputmode="tel" :value="$courier->phone" placeholder="050 123 45 67" required />
                </x-field>
                <input type="hidden" name="phone_country" value="{{ $courier->phone_country ?? $currentCompany->country }}">
                <x-field :label="__('Email')" name="email"><x-input name="email" type="email" :value="$courier->email" /></x-field>
                <x-field :label="__('App language')" name="locale">
                    <x-select name="locale" :options="['az' => 'Azərbaycan', 'en' => 'English', 'ru' => 'Русский', 'tr' => 'Türkçe']" :value="$courier->locale ?? $currentCompany->locale" />
                </x-field>
                <x-field :label="__('Courier code')" name="code" :hint="__('Your internal number for this courier.')"><x-input name="code" :value="$detail?->code" /></x-field>
            </div>
        </section>

        <div class="flex flex-col gap-6">
            <section class="plate">
                <div class="plate-head"><h2 class="t-label text-[var(--text)]">{{ __('Vehicle and capacity') }}</h2></div>
                <div class="grid grid-cols-2 gap-5 p-5">
                    <x-field :label="__('Vehicle')" name="vehicle_type_slug" class="col-span-2"><x-select name="vehicle_type_slug" :options="$vehicles" :value="$detail?->vehicle_type_slug ?? 'motorcycle'" /></x-field>
                    <x-field :label="__('Plate number')" name="vehicle_license_plate"><x-input name="vehicle_license_plate" :value="$detail?->vehicle_license_plate" /></x-field>
                    <x-field :label="__('Colour')" name="vehicle_color"><x-input name="vehicle_color" :value="$detail?->vehicle_color" /></x-field>
                    <x-field :label="__('Capacity')" name="capacity" :hint="__('Tasks at once. Empty = no limit.')"><x-input name="capacity" type="number" min="0" :value="$detail?->capacity" /></x-field>
                    <x-field :label="__('Pool capacity')" name="pool_capacity" :hint="__('How many pool orders the courier may claim.')"><x-input name="pool_capacity" type="number" min="0" :value="$detail?->pool_capacity" /></x-field>
                </div>
            </section>

            <section class="plate">
                <div class="plate-head"><h2 class="t-label text-[var(--text)]">{{ __('Teams') }}</h2></div>
                <div class="flex flex-col gap-2 p-5">
                    <input type="hidden" name="team_ids" value="">
                    @forelse ($teams as $team)
                        <label class="flex items-center gap-3">
                            <input type="checkbox" class="check" name="team_ids[]" value="{{ $team->id }}" @checked(in_array($team->id, $teamIds))>
                            <span class="h-[5px] w-6 rounded-full" style="background: {{ $team->color }}"></span>{{ $team->name }}
                        </label>
                    @empty
                        <p class="text-[13px] text-[var(--text-3)]">{{ __('No teams yet.') }}</p>
                    @endforelse
                </div>
            </section>

            <x-field :label="__('Notes')" name="notes"><textarea name="notes" id="notes" class="input">{{ old('notes', $detail?->notes) }}</textarea></x-field>

            <div class="flex gap-3">
                <button class="btn btn-primary flex-1">{{ $courier->exists ? __('Save courier') : __('Add courier') }}</button>
                <a href="{{ route('couriers.index') }}" class="btn">{{ __('Cancel') }}</a>
            </div>
        </div>
    </form>

    @if ($courier->exists)
        <form method="POST" action="{{ route('couriers.destroy', $courier) }}" class="mx-auto mt-6 max-w-[960px]" onsubmit="return confirm(@js(__('End this courier\'s access? Their task history stays.')))">
            @csrf @method('DELETE')
            <button class="btn btn-danger"><x-icon name="trash-2" :size="16" />{{ __('End access') }}</button>
        </form>
    @endif
</x-layouts::app>
