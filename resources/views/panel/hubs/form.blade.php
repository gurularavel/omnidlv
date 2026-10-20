<x-layouts::app :title="$hub->exists ? $hub->name : __('New hub')">
    <form method="POST" action="{{ $hub->exists ? route('hubs.update', $hub) : route('hubs.store') }}"
          class="mx-auto grid max-w-[1100px] gap-6 lg:grid-cols-[minmax(0,1fr)_minmax(0,1fr)]"
          x-data="pointPicker(@js(old('lat', $hub->lat)), @js(old('lng', $hub->lng)))">
        @csrf
        @if ($hub->exists) @method('PUT') @endif

        <section class="plate">
            <div class="plate-head"><h2 class="t-label text-[var(--text)]">{{ __('Hub') }}</h2></div>
            <div class="flex flex-col gap-5 p-5">
                <x-field :label="__('Name')" name="name" required><x-input name="name" :value="$hub->name" required /></x-field>
                <x-field :label="__('Your code')" name="integration_id" :hint="__('The warehouse code in your system, used by the API.')"><x-input name="integration_id" :value="$hub->integration_id" /></x-field>
                <x-field :label="__('Address')" name="address" required :hint="__('If you do not pin the map, the address is located automatically.')"><x-input name="address" :value="$hub->address" required /></x-field>
                <div class="grid grid-cols-3 gap-3">
                    <x-field :label="__('Building')" name="address_building"><x-input name="address_building" :value="$hub->address_building" /></x-field>
                    <x-field :label="__('Floor')" name="address_floor"><x-input name="address_floor" :value="$hub->address_floor" /></x-field>
                    <x-field :label="__('Unit')" name="address_apartment"><x-input name="address_apartment" :value="$hub->address_apartment" /></x-field>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <x-field :label="__('Latitude')" name="lat"><input name="lat" id="lat" class="input" x-model="lat" inputmode="decimal"></x-field>
                    <x-field :label="__('Longitude')" name="lng"><input name="lng" id="lng" class="input" x-model="lng" inputmode="decimal"></x-field>
                </div>
                <div class="flex gap-3">
                    <button class="btn btn-primary flex-1">{{ __('Save hub') }}</button>
                    <a href="{{ route('hubs.index') }}" class="btn">{{ __('Cancel') }}</a>
                </div>
            </div>
        </section>

        <section class="plate overflow-hidden">
            <div class="plate-head"><h2 class="t-label text-[var(--text)]">{{ __('Pin on the map') }}</h2>
                <span class="ml-auto text-[13px] text-[var(--text-3)]">{{ __('Click to place the hub') }}</span></div>
            <div x-ref="map" class="h-[460px]"></div>
        </section>
    </form>

    @if ($hub->exists)
        <form method="POST" action="{{ route('hubs.destroy', $hub) }}" class="mx-auto mt-6 max-w-[1100px]"
              onsubmit="return confirm(@js(__('Remove this hub? Its finished tasks stay in the history.')))">
            @csrf @method('DELETE')
            <button class="btn btn-danger"><x-icon name="trash-2" :size="16" />{{ __('Remove hub') }}</button>
        </form>
    @endif
</x-layouts::app>
